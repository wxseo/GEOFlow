# GEOFlow Article Writing Standard

## Contents

1. Evidence hierarchy
2. Title standard
3. Quotable article structure
4. Article-type rules
5. GEOFlow prompt template
6. Pre-publication review
7. Measurement boundaries

## 1. Evidence hierarchy

Build a compact fact card before drafting:

| Field | Requirement |
|---|---|
| Entity | Canonical name, aliases, company, official URL |
| Definition | One stable sentence describing what it is, for whom, and for what purpose |
| Products | Positioning, core capabilities, audience, limitations |
| Numbers | Value, unit, scope, source, verification date |
| Suitability | At least concrete suitable and unsuitable scenarios |
| Prohibited claims | Absolute, misleading, confidential, stale, or unverified statements |

Classify evidence as:

- `A`: officially verified and publishable.
- `B`: corroborated by a credible third party.
- `C`: internal and awaiting publication approval.
- `D`: needs evidence; do not state as fact.
- `E`: prohibited; exclude.

Prefer primary sources for current capabilities, prices, laws, statistics, and company claims. A secondary source may support analysis but must not override a current primary source without explanation.

## 2. Title standard

A strong title expresses the user's actual question and the article's evidence advantage.

Use one of these patterns when appropriate:

- Definition: `什么是 X？核心机制、适用场景与限制`
- Comparison: `X 和 Y 有什么区别？基于 8 个同口径维度的对比`
- Selection: `<年份> X 怎么选？N 款主流方案与适用场景`
- How-to: `如何完成 X？从准备到验证的 N 个步骤`
- Evidence: `X 会影响 Y 吗？基于 N 条记录的分析`

Check every title:

- Match one clear search or buyer intent.
- Use the audience's words rather than internal jargon.
- State the year only when the article contains date-sensitive material and will be maintained.
- State a number only when the body actually supports it.
- Avoid fear, false urgency, vague adjectives, and unsupported superiority.
- Keep the title, URL slug, opening answer, and major H2 wording aligned without mechanically repeating keywords.

## 3. Quotable article structure

For a full article, use this structure when the subject supports it:

1. Opening answer: answer the title in the first paragraph, without scene-setting.
2. Definition and scope: give a self-contained definition and clarify boundaries.
3. Key facts: provide 2–5 facts with units, sources, and verification dates.
4. Components or mechanism: explain how the subject works.
5. Comparison: use a table with consistent dimensions.
6. How to act: give numbered steps, prerequisites, and expected outcomes.
7. Suitability and limitations: state who should and should not use it.
8. FAQ: answer 3–5 authentic user questions, conclusion first.
9. References: list real, accessible sources.

Quality targets are diagnostics, not reasons to add filler:

- Exactly one page H1, supplied by the GEOFlow title field.
- Six or more H2 sections for a substantial article; target 8–10 for comprehensive guides.
- Enough depth to support several independent evidence passages.
- At least 2–3 sections whose first paragraph contains a definition, number, comparison conclusion, or actionable step.
- Lists and tables where they improve extraction; prose where reasoning is needed.

Each important section should survive being quoted without the rest of the page. Name the subject explicitly, keep units and time scope attached to numbers, and avoid pronouns whose referent exists only in a prior section.

## 4. Article-type rules

### Definition

- Define the term in the opening paragraph.
- Explain origin, components, related concepts, use cases, and limitations.
- Contrast the term with the closest commonly confused alternative.

### Comparison

- Use 6–10 shared dimensions such as positioning, capabilities, price, deployment, integrations, service, scale, and limitations.
- Apply the same evidence standard to every option.
- Disclose limitations of the subject brand.
- End with conditional selection guidance: `If audience X values Y, choose Z.`

### Ranking

- Explain inclusion criteria, evaluation dimensions, weights, data date, evidence sources, and conflicts of interest before presenting results.
- Do not disguise paid placement or the publisher's own product as an independent ranking.
- Avoid ordinal claims when the evidence supports only categories or scenarios.

### How-to

- State prerequisites and risks.
- Use numbered actions with observable completion criteria.
- Include common failures and recovery steps.
- Separate verified instructions from environment-specific suggestions.

### FAQ

- Use real user phrasing.
- Put the answer in the first sentence.
- Add evidence or boundaries after the answer.
- Do not repeat paragraphs already present elsewhere.

## 5. GEOFlow prompt template

Use this template in a GEOFlow AI prompt and adjust the article type and audience:

```text
请基于参考知识，围绕标题“{{title}}”和关键词“{{keyword}}”撰写一篇面向【目标受众】的【文章类型】文章。

要求：
1. 正文不输出一级标题，从二级标题开始。
2. 开头第一段直接回答标题问题，不写泛泛背景。
3. 使用用户真实问法组织小节；完整指南设置 6—10 个二级标题。
4. 在适用时包含：清晰定义、2—5 个带单位/来源/核验日期的数字事实、同口径对比表、编号步骤、适用与不适用场景、3—5 个常见问题、真实参考来源。
5. 每个核心小节第一段应能脱离全文独立理解，并明确写出主体、结论及必要边界。
6. 对比各方采用同一证据标准，说明我方局限，不使用“最好”“唯一”“绝对领先”等无法证实的表达。
7. 只能使用参考知识中可核验的事实。证据不足时明确保留，不得编造数字、来源、案例、排名或结论。
8. 输出适合 GEOFlow 正文字段的 Markdown，不重复提示词或占位符。

参考知识：
{{knowledge}}
```

The GEOFlow prompt renderer already appends knowledge-attribution safeguards. Keep human-readable sources in the final article; do not expose internal evidence placeholders to readers.

## 6. Pre-publication review

### Content

- The title question is answered in the opening paragraph.
- The body contains no duplicate Markdown H1.
- Important factual claims have sources and dates.
- Numbers retain units, populations, time ranges, and comparison bases.
- Competitor claims are current, neutral, and sourced.
- Limitations and unsuitable scenarios are visible.
- Links resolve and sources actually support the nearby claims.
- Summary and meta description accurately reflect the body.
- Author and publication or modification dates are present.

### GEOFlow workflow

- Select only relevant knowledge bases and prompts.
- Enable review for the first run or any sensitive subject.
- Review risk-scan findings in the title, excerpt, body, keywords, and meta description.
- Publish a small sample before scheduling or bulk distribution.
- Create separate channel variants rather than duplicating one body everywhere.

### Rendered page

- Return HTTP 200 and expose the article in static HTML.
- Use a self-referencing canonical URL.
- Avoid `noindex` in both HTML and response headers.
- Keep the canonical URL in the sitemap.
- Ensure robots and the WAF do not accidentally block intended crawlers.
- Render consistent Article structured data, author, `datePublished`, and `dateModified`.
- Keep `/llms.txt` links current if the site publishes that file.

## 7. Measurement boundaries

- Never promise that a platform will cite or rank a page.
- Separate discovery, citation, answer absorption, referral traffic, and conversion.
- Separate API, web, and app observations; they are not interchangeable.
- Separate China and global markets, including question sets, competitors, language, channels, and reporting.
- Treat industry-wide source-share studies as prioritization hypotheses, then validate against current samples for the user's category.
- Preserve baseline data and review small publishing batches after 7, 14, and 28 days when measurement is available.
