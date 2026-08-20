<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\Category;
use App\Models\EnterpriseKnowledgeProject;
use App\Models\KnowledgeBase;
use App\Models\Task;
use App\Models\Title;
use App\Models\TitleLibrary;
use App\Services\GeoFlow\WorkerExecutionService;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkerExecutionSourceTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_article_keeps_the_selected_source_title_relation(): void
    {
        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'model' => 'test-chat-model',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => "# 自动文章\n\n核心结论 [K1]。完整正文【K2】。"],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
            ]),
        ]);
        Category::query()->create([
            'name' => '默认分类',
            'slug' => 'default-category',
            'sort_order' => 1,
        ]);
        $model = AiModel::query()->create([
            'name' => 'Worker Chat',
            'version' => 'test',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('test-api-key'),
            'model_id' => 'test-chat-model',
            'model_type' => 'chat',
            'api_url' => 'https://ai.test',
            'daily_limit' => 10,
            'status' => 'active',
        ]);
        $library = TitleLibrary::query()->create(['name' => '自动标题库']);
        $title = Title::query()->create([
            'library_id' => $library->id,
            'title' => '自动文章',
            'keyword' => 'GEO',
        ]);
        $task = Task::query()->create([
            'name' => '自动文章任务',
            'title_library_id' => $library->id,
            'ai_model_id' => $model->id,
            'draft_limit' => 10,
            'article_limit' => 10,
            'status' => 'active',
            'schedule_enabled' => 1,
        ]);

        $result = app(WorkerExecutionService::class)->executeTask((int) $task->id);
        $article = $title->articles()->whereKey((int) $result['article_id'])->firstOrFail();

        $this->assertSame((int) $title->id, (int) $article->source_title_id);
        $this->assertSame(1, (int) $title->fresh()->used_count);
        $this->assertSame(1, (int) $title->fresh()->usage_count);
        $this->assertSame("# 自动文章\n\n核心结论。完整正文。", $article->content);
        $this->assertStringNotContainsString('K1', (string) $article->excerpt);
        $this->assertStringNotContainsString('K2', (string) $article->excerpt);
        $this->assertStringNotContainsString('K1', (string) $article->meta_description);
        $this->assertStringNotContainsString('K2', (string) $article->meta_description);
    }

    public function test_brand_asset_noncompliance_keeps_an_auto_review_task_pending(): void
    {
        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response([
                'model' => 'test-chat-model',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => "# QRQC \u5b9e\u8df5\n\n\u8fd9\u662f\u4e00\u7bc7\u6ca1\u6709\u54c1\u724c\u4fe1\u606f\u7684\u6b63\u6587\u3002"],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
            ]),
        ]);
        Category::query()->create([
            'name' => '默认分类',
            'slug' => 'default-category',
            'sort_order' => 1,
        ]);
        $model = AiModel::query()->create([
            'name' => 'Worker Chat',
            'version' => 'test',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('test-api-key'),
            'model_id' => 'test-chat-model',
            'model_type' => 'chat',
            'api_url' => 'https://ai.test',
            'daily_limit' => 10,
            'status' => 'active',
        ]);
        $library = TitleLibrary::query()->create(['name' => 'QRQC 标题库']);
        Title::query()->create([
            'library_id' => $library->id,
            'title' => 'QRQC 如何做闭环管理？',
            'keyword' => 'QRQC',
        ]);
        $knowledgeBase = KnowledgeBase::query()->create([
            'name' => 'BIQS 企业知识库',
            'source_url' => 'https://www.biqs.cn/',
            'content' => "## 企业介绍\nBIQS 专注质量管理数字化。\n\n## 产品能力\nQRQC 闭环管理。",
        ]);
        EnterpriseKnowledgeProject::query()->create([
            'name' => 'BIQS',
            'status' => 'published',
            'published_knowledge_base_id' => (int) $knowledgeBase->id,
        ]);
        $task = Task::query()->create([
            'name' => 'BIQS QRQC 任务',
            'title_library_id' => $library->id,
            'ai_model_id' => $model->id,
            'knowledge_base_id' => $knowledgeBase->id,
            'need_review' => 0,
            'draft_limit' => 10,
            'article_limit' => 10,
            'status' => 'active',
            'schedule_enabled' => 1,
        ]);

        $result = app(WorkerExecutionService::class)->executeTask((int) $task->id);
        $article = $task->articles()->findOrFail((int) $result['article_id']);

        $this->assertSame('pending', (string) $article->review_status);
        $this->assertSame([
            '未自然提及品牌：BIQS',
            '未使用企业资产中批准的官方链接',
        ], $result['meta']['brand_compliance_issues']);
        Http::assertSent(function ($request): bool {
            $payload = json_encode($request->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($payload)
                && str_contains($payload, '【已审核的品牌与产品资产】')
                && str_contains($payload, 'BIQS 专注质量管理数字化')
                && str_contains($payload, 'https://www.biqs.cn/');
        });
    }
}
