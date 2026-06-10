<?php

namespace Tests\Feature\Messaging;

use App\Jobs\Messaging\ScanAttachmentForMalware;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Messaging\AttachmentUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentUploadServiceCoverageBatch9Test extends TestCase
{
    use RefreshDatabase;

    private function makeMessage(): Message
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $org->id]);

        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'upload-test',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $user->id,
        ]);

        return Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_attach_stores_pdf_and_creates_pending_attachment(): void
    {
        Bus::fake();
        Storage::fake('public');
        config(['messaging.attachments.disk' => 'public']);

        $message = $this->makeMessage();
        $uploader = $message->sender;

        $file = UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf');

        $attachment = app(AttachmentUploadService::class)->attach($message, $uploader, $file);

        $this->assertInstanceOf(MessageAttachment::class, $attachment);
        $this->assertSame($message->id, $attachment->message_id);
        $this->assertSame($uploader->id, $attachment->uploaded_by);
        $this->assertSame('public', $attachment->disk);
        $this->assertSame('contract.pdf', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame(MessageAttachment::AV_STATUS_PENDING, $attachment->av_status);
        $this->assertNull($attachment->image_width);
        $this->assertNull($attachment->thumbnail_path);

        Storage::disk('public')->assertExists($attachment->path);
        $this->assertStringStartsWith('message-attachments/'.now()->format('Y/m'), $attachment->path);
    }

    public function test_attach_dispatches_av_scan_when_engine_configured(): void
    {
        Bus::fake();
        Storage::fake('public');
        config(['messaging.av.engine' => 'clamav', 'messaging.av.required' => false]);

        $message = $this->makeMessage();
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        app(AttachmentUploadService::class)->attach($message, $message->sender, $file);

        Bus::assertDispatched(ScanAttachmentForMalware::class);
    }

    public function test_attach_does_not_dispatch_av_scan_when_disabled(): void
    {
        Bus::fake();
        Storage::fake('public');
        config(['messaging.av.engine' => null, 'messaging.av.required' => false]);

        $message = $this->makeMessage();
        $file = UploadedFile::fake()->create('list.csv', 5, 'text/csv');

        app(AttachmentUploadService::class)->attach($message, $message->sender, $file);

        Bus::assertNotDispatched(ScanAttachmentForMalware::class);
    }

    public function test_attach_runs_image_metadata_branch_for_image_mime(): void
    {
        Bus::fake();
        Storage::fake('public');
        config(['messaging.av.engine' => null]);

        $message = $this->makeMessage();
        // GD-free fake: a non-decodable "image" still exercises the image branch
        // (str_starts_with mime, getimagesize fallback) without a real thumbnail.
        $file = UploadedFile::fake()->create('avatar.jpg', 50, 'image/jpeg');

        $attachment = app(AttachmentUploadService::class)->attach($message, $message->sender, $file);

        $this->assertTrue($attachment->isImage());
        $this->assertSame('image/jpeg', $attachment->mime_type);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_attach_rejects_disallowed_mime_type(): void
    {
        Storage::fake('public');
        $message = $this->makeMessage();

        $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Type de fichier non autorisé');

        app(AttachmentUploadService::class)->attach($message, $message->sender, $file);
    }

    public function test_attach_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $message = $this->makeMessage();

        $sizeKb = (int) (AttachmentUploadService::MAX_SIZE_BYTES / 1024) + 1024;
        $file = UploadedFile::fake()->create('huge.pdf', $sizeKb, 'application/pdf');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('trop volumineux');

        app(AttachmentUploadService::class)->attach($message, $message->sender, $file);
    }

    public function test_attach_rejects_invalid_upload(): void
    {
        Storage::fake('public');
        $message = $this->makeMessage();

        // UPLOAD_ERR_NO_FILE => isValid() returns false
        $file = new UploadedFile(
            __FILE__,
            'broken.pdf',
            'application/pdf',
            UPLOAD_ERR_NO_FILE,
            true
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("n'a pas pu être uploadé");

        app(AttachmentUploadService::class)->attach($message, $message->sender, $file);
    }

    public function test_max_size_constant_is_25_megabytes(): void
    {
        $this->assertSame(25 * 1024 * 1024, AttachmentUploadService::MAX_SIZE_BYTES);
        $this->assertContains('application/pdf', AttachmentUploadService::ALLOWED_MIMES);
        $this->assertContains('image/jpeg', AttachmentUploadService::ALLOWED_MIMES);
    }
}
