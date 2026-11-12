<?php

namespace App\Models;

use App\Enums\UnitStatus;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = ['article_id', 'code', 'qr_token', 'status', 'acquired_at', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'acquired_at' => 'date',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    /** URL que se codifica en la etiqueta. */
    public function qrUrl(): string
    {
        return route('qr.resolve', $this->qr_token);
    }

    /** SVG del QR listo para incrustar en la página. */
    public function qrSvg(): string
    {
        $options = new QROptions([
            'outputType' => 'svg',
            'outputBase64' => false,
            'eccLevel' => 'M',
            'addQuietzone' => true,
            'quietzoneSize' => 1,
            'svgAddXmlHeader' => false,
        ]);

        return (new QRCode($options))->render($this->qrUrl());
    }
}
