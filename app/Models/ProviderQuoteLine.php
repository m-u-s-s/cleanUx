<?php

namespace App\Models;

use Database\Factories\ProviderQuoteLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UNE LIGNE DE DEVIS — un métier, une quantité, un prix.
 *
 * LE MÉTIER EST OBLIGATOIRE, et c'est ce qui distingue cette table d'un tableau de facturation.
 * C'est lui qui décide qui peut exécuter la ligne : sans lui, l'acceptation ne produit aucune
 * mission et le devis reste un PDF qu'on se renvoie par courriel.
 *
 * `suggested_price_cents` GARDE CE QUE LE MOTEUR PROPOSAIT. L'écart avec le prix retenu rend la
 * remise lisible, et dit — au bout de quelques dizaines de devis — si la société vend
 * systématiquement sous son propre tarif.
 *
 * @property int $id
 * @property int $provider_quote_id
 * @property int $trade_id
 * @property string $label
 * @property float $quantity
 * @property int $unit_price_cents
 * @property int $total_cents
 * @property int|null $suggested_price_cents
 * @property int|null $booking_id
 */
class ProviderQuoteLine extends Model
{
    /** @use HasFactory<ProviderQuoteLineFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_quote_id',
        'trade_id',
        'service_catalog_id',
        'label',
        'description',
        'quantity',
        'unit',
        'unit_price_cents',
        'total_cents',
        'suggested_price_cents',
        'sort_order',
        'booking_id',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price_cents' => 'integer',
        'total_cents' => 'integer',
        'suggested_price_cents' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<ProviderQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(ProviderQuote::class, 'provider_quote_id');
    }

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }
}
