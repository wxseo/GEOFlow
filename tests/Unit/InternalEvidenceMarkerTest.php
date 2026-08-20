<?php

namespace Tests\Unit;

use App\Support\GeoFlow\InternalEvidenceMarker;
use PHPUnit\Framework\TestCase;

class InternalEvidenceMarkerTest extends TestCase
{
    public function test_it_removes_supported_internal_evidence_marker_formats(): void
    {
        $content = '结论一 [K1]。结论二【K2】；结论三（K3），结论四( K4 )。连续引用[K5][K6]。';

        $this->assertSame(
            '结论一。结论二；结论三，结论四。连续引用。',
            InternalEvidenceMarker::remove($content)
        );
    }

    public function test_it_preserves_unwrapped_product_codes(): void
    {
        $content = '设备型号 K1 与 K20 均保持原样。';

        $this->assertSame($content, InternalEvidenceMarker::remove($content));
        $this->assertSame([], InternalEvidenceMarker::extract($content));
    }

    public function test_it_extracts_each_marker_for_risk_scanning(): void
    {
        $this->assertSame(
            ['[K1]', '【K2】', '（ K3 ）'],
            InternalEvidenceMarker::extract('正文 [K1]、【K2】和（ K3 ）。')
        );
    }
}
