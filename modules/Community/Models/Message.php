<?php

namespace Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A chat message: text, a photo, or both. Photos live on the private disk and
 * are only served to members of the conversation (ChatController::photo).
 */
class Message extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const PHOTO_MAX_KB = 5120;

    protected $table = 'community_messages';

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'hidden_at'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->useDisk(config('napps.community.media_disk', 'local'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * A light preview for slow connections; the original opens on tap.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if (! self::canMakePreviews()) {
            return; // the original is served instead
        }

        $this->addMediaConversion('preview')
            ->performOnCollections('photo')
            ->nonQueued()
            ->fit(Fit::Max, 720, 720)
            ->format('jpg')
            ->quality(78);
    }

    /**
     * Previews need an image driver that reads JPEG/PNG/WebP and writes JPEG.
     * Some PHP builds ship GD without them; then photos are served as uploaded.
     */
    public static function canMakePreviews(): bool
    {
        if (config('media-library.image_driver') === 'imagick') {
            return extension_loaded('imagick');
        }

        return function_exists('imagejpeg') && function_exists('imagecreatefromjpeg')
            && function_exists('imagecreatefrompng') && function_exists('imagecreatefromwebp');
    }

    public function photo(): ?Media
    {
        return $this->relationLoaded('media') ? $this->media->firstWhere('collection_name', 'photo') : null;
    }

    /**
     * Text for previews and notifications ("Photo" when there is no caption).
     */
    public function preview(int $limit = 60): string
    {
        $text = trim((string) $this->body);

        return $text !== '' ? str($text)->limit($limit)->toString() : __('Photo');
    }
}
