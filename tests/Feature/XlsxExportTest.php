<?php

namespace Tests\Feature;

use App\Jobs\GenerateAnalyticsExport;
use App\Models\AnalyticsExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class XlsxExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_job_creates_eight_sheet_private_workbook(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $export = AnalyticsExport::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'queued', 'format' => 'xlsx']);
        app()->call([new GenerateAnalyticsExport($export->id), 'handle']);
        $export->refresh();
        $this->assertSame('completed', $export->status);
        $this->assertStringEndsWith('.xlsx', $export->storage_path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($export->storage_path)) === true);
        $workbook = $zip->getFromName('xl/workbook.xml');
        foreach (['Summary', 'People', 'Conversation Sessions', 'Groups', 'Facebook Interactions', 'Interaction Scores', 'Monthly Trends', 'Data Coverage'] as $sheet) {
            $this->assertStringContainsString($sheet, $workbook);
        }$this->assertStringNotContainsString('private fixture text', (string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
    }
}
