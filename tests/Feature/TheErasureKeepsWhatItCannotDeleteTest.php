<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Livewire\Dashboard\VisitorDetailPage;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * L'effacement d'un visiteur echoue, et l'ecran le dit sans rien perdre.
 *
 * **Un fichier a lui seul, et pour une raison mecanique.**
 * `ForgetVisitorAction` supprime dans une transaction, deliberement, pour se
 * comporter pareil sur tous les moteurs. Casser une table demande du DDL, et
 * une instruction DDL valide implicitement la transaction que le banc tient
 * ouverte : les points de reprise partent avec elle, et le code echoue sur
 * « SAVEPOINT trans2 does not exist » au lieu d'echouer sur sa suppression. Ce
 * n'est plus la meme chose qu'on mesure.
 *
 * Cette classe garde donc `RefreshDatabase` pour la migration, et **neutralise
 * son enveloppe transactionnelle** · la transaction de l'action en est alors
 * une vraie, et le renommage ne derange rien. Le prix est qu'il faut ranger
 * derriere soi, ce que `tearDown` fait.
 *
 * Deux autres voies ont ete essayees et ecartees, pour qu'on ne les reprenne
 * pas. Decaler le prefixe de tables ne marche pas ici · Livewire rehydrate le
 * visiteur a chaque interaction, donc la lecture casserait avant l'ecriture et
 * l'essai passerait pour la mauvaise raison. Et `DatabaseMigrations` ne monte
 * pas les migrations de fixtures sous Testbench, si bien que la table des
 * administrateurs manque avant meme le premier appel.
 */
final class TheErasureKeepsWhatItCannotDeleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * L'enveloppe transactionnelle du banc, retiree.
     *
     * `RefreshDatabase` migre puis ouvre une transaction qu'il annule a la fin,
     * ce qui rend chaque essai gratuit. Ici, cette transaction est precisement
     * ce qui empeche de mesurer ce qu'on veut.
     */
    public function beginDatabaseTransaction(): void
    {
        //
    }

    protected function tearDown(): void
    {
        // Rien n'est annule tout seul · on efface ce que cet essai a ecrit,
        // sans quoi il le laisserait au suivant.
        if (Schema::hasTable('falcon_analytics_visitors')) {
            DB::table('falcon_analytics_visitors')->delete();
        }

        if (Schema::hasTable('test_admins')) {
            DB::table('test_admins')->delete();
        }

        parent::tearDown();
    }

    public function test_it_shows_an_inline_error_and_keeps_the_visitor_when_the_erasure_fails(): void
    {
        $visitor = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'session_count' => 0,
        ]);

        $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin');

        // La table des evenements est la premiere que l'action vide · la
        // suppression echoue la, et le visiteur ne doit pas partir pour autant.
        $this->withoutTable('falcon_analytics_events', function () use ($visitor): void {
            Livewire::test(VisitorDetailPage::class, ['visitor' => $visitor])
                ->call('forget')
                ->assertHasErrors('visitor-erasure-failed')
                ->assertNoRedirect();
        });

        $this->assertTrue(Visitor::whereKey($visitor->id)->exists());
    }
}
