<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\OrderDraftMedia;
use App\Models\Trade;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * « Une photo vaut dix questions » — et encore faut-il qu'elle arrive quelque part.
 *
 * Le champ existait, le tableau `order_draft_media` existait, le modèle et la relation aussi. Rien
 * ne les reliait : `wire:model` sur un `<input type="file">` sans le trait d'upload ne fait
 * strictement rien. Le client choisissait une photo, voyait « Envoi en cours… », et le fichier
 * disparaissait — sans erreur, sans trace, et sans que le prestataire n'en voie jamais la couleur.
 *
 * Ces tests verrouillent le chemin complet : le fichier est stocké, la ligne existe, elle est
 * rattachée à la bonne ligne de commande, et le client peut revenir en arrière.
 */
class PhotoAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        Storage::fake('public');
    }

    /** LE test qui manquait : la photo choisie existe encore après la requête. */
    public function test_an_attached_photo_is_stored_and_recorded(): void
    {
        $trade = $this->peinture();

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $trade->id)
            ->set('photos', [UploadedFile::fake()->create('mur.jpg', 240, 'image/jpeg')])
            ->call('attachPhotos');

        $media = OrderDraftMedia::first();

        $this->assertNotNull($media, 'La photo n’a laissé aucune trace : le champ ne mène nulle part.');
        $this->assertSame($trade->id, $media->item->trade_id);
        Storage::disk('public')->assertExists($media->path);
    }

    /** Le client change d'avis : la ligne ET le fichier disparaissent. */
    public function test_removing_a_photo_takes_the_file_with_it(): void
    {
        $component = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->set('photos', [UploadedFile::fake()->create('mur.jpg', 240, 'image/jpeg')])
            ->call('attachPhotos');

        $media = OrderDraftMedia::firstOrFail();
        $path = $media->path;

        $component->call('removePhoto', $media->id);

        $this->assertNull(OrderDraftMedia::find($media->id));
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * Un fichier qui n'est pas une image est refusé EN LE DISANT.
     *
     * Un refus muet fait recommencer trois fois avec le même fichier.
     */
    public function test_a_non_image_is_refused_with_an_explanation(): void
    {
        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->set('photos', [UploadedFile::fake()->create('devis.pdf', 40, 'application/pdf')])
            ->call('attachPhotos')
            ->assertHasErrors('photos.0');

        $this->assertSame(0, OrderDraftMedia::count());
    }

    /** Les photos déjà jointes se revoient : sans aperçu, on rejoint la même deux fois. */
    public function test_attached_photos_are_shown_back_to_the_client(): void
    {
        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->set('photos', [UploadedFile::fake()->create('mur.jpg', 240, 'image/jpeg')])
            ->call('attachPhotos')
            ->assertSee('Retirer cette photo');
    }

    /**
     * La photo suit le MÉTIER, pas la commande.
     *
     * En multi-services, le mur à peindre et la douche à refaire ne se photographient pas
     * ensemble : chaque prestataire doit voir ce qui le concerne.
     */
    public function test_a_photo_belongs_to_the_trade_it_was_attached_to(): void
    {
        $peinture = $this->peinture();
        $plomberie = Trade::where('slug', 'plumbing')->firstOrFail();

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $peinture->id)
            ->set('photos', [UploadedFile::fake()->create('mur.jpg', 240, 'image/jpeg')])
            ->call('attachPhotos')
            ->call('selectTrade', $plomberie->id)
            ->set('photos', [UploadedFile::fake()->create('douche.jpg', 240, 'image/jpeg')])
            ->call('attachPhotos');

        $this->assertSame(
            1,
            OrderDraftMedia::whereHas('item', fn ($q) => $q->where('trade_id', $peinture->id))->count(),
        );
        $this->assertSame(
            1,
            OrderDraftMedia::whereHas('item', fn ($q) => $q->where('trade_id', $plomberie->id))->count(),
        );
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }
}
