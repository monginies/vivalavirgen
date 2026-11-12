<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ArticlePhoto extends Model
{
    protected $fillable = ['article_id', 'path', 'thumb_path', 'position'];

    protected static function booted(): void
    {
        static::deleted(function (ArticlePhoto $photo) {
            Storage::disk('public')->delete(array_unique([$photo->path, $photo->thumb_path]));
        });
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    public function thumbUrl(): string
    {
        return asset('storage/'.$this->thumb_path);
    }
}
