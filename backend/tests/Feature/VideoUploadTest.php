<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VideoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_upload_strips_location_metadata_and_keeps_a_poster_for_analysis(): void
    {
        if (! VideoProcessor::available()) $this->markTestSkipped('ffmpeg not installed');
        $bin = is_executable('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg';
        $src = sys_get_temp_dir().'/rl_src_'.uniqid().'.mov';
        (new Process([$bin, '-v', 'error', '-y', '-f', 'lavfi', '-i', 'testsrc=size=320x240:rate=10', '-t', '2', '-metadata', 'location=+23.7465+090.3760/',
            '-metadata', 'com.apple.quicktime.location.ISO6709=+23.7465+090.3760/', $src]))->mustRun();
        $u = User::factory()->create();
        $r = $this->actingAs($u)->post('/api/v1/media', ['video' => new UploadedFile($src, 'clip.mov', 'video/quicktime', null, true)], ['Accept' => 'application/json'])->assertCreated();
        $this->assertNotNull($r->json('video_url'));
        $this->assertNotNull($r->json('url'));
        $m = \App\Models\IncidentMedia::first();
        $this->assertSame('image/jpeg', $m->mime);
        $stored = \Illuminate\Support\Facades\Storage::disk('local')->path($m->video_path);
        $probe = new Process([$bin, '-i', $stored]); $probe->run();
        $this->assertStringNotContainsString('23.7465', $probe->getErrorOutput());
        $this->get($r->json('video_url'))->assertOk();
        @unlink($src);
    }
}
