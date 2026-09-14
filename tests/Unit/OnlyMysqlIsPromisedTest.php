<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\DatabaseEngine;
use Falcon\Analytics\Tests\Fixtures\ExposedSessionQueries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Un seul moteur promis, et le code ne prétend plus le contraire.
 *
 * **Trois chiffres répondaient à une seule question** · le code portait des
 * branches pour cinq pilotes, la notice en annonçait trois, et la suite n'en
 * faisait tourner qu'un. Aucun essai n'a jamais exercé les branches PostgreSQL,
 * SQL Server ou SQLite — cette absence était la preuve qu'elles ne tenaient
 * rien. Elles sont parties, et ces essais gardent ce qui reste.
 *
 * MariaDB suit MySQL sans une ligne de code · `DATE`, `DATE_FORMAT` et
 * `TIMESTAMPDIFF` y portent le même nom et le même sens.
 *
 * **Pourquoi une garde plutôt qu'une erreur SQL** · une application Laravel
 * neuve arrive réglée sur SQLite. Sans elle, l'installation publierait tout,
 * migrerait, et l'hôte rencontrerait le défaut bien plus tard, sur un écran,
 * par un message de syntaxe que personne ne relie à ce paquet.
 *
 * Aucune application n'est démarrée ici · les deux méthodes employées prennent
 * le pilote en argument, précisément pour être éprouvables sans base.
 */
final class OnlyMysqlIsPromisedTest extends TestCase
{
    public function test_the_three_expressions_are_written_in_mysql(): void
    {
        $expressions = new ExposedSessionQueries;

        $this->assertSame('DATE(started_at)', $expressions->day());
        $this->assertSame("DATE_FORMAT(occurred_at, '%Y-%m-%d %H:%i')", $expressions->minute());
        $this->assertSame('TIMESTAMPDIFF(SECOND, started_at, last_activity_at)', $expressions->duration());
    }

    /**
     * Et elles ne portent plus la moindre trace des autres moteurs · c'est ce
     * qui empêche une branche de revenir sans que la promesse suive.
     */
    public function test_no_other_dialect_survives_in_the_expressions(): void
    {
        $expressions = new ExposedSessionQueries;

        $written = implode(' ', [
            $expressions->day(),
            $expressions->minute(),
            $expressions->duration(),
        ]);

        /*
         * La forme SQL Server de la minute, `FORMAT(...)`, n'est pas dans cette
         * liste · elle est contenue dans le `DATE_FORMAT` de MySQL, donc la
         * chercher ferait tomber l'essai sur la bonne réponse. Les cinq autres
         * empreintes ne se confondent avec rien.
         */
        foreach (['to_char', 'strftime', 'CONVERT(', 'DATEDIFF', 'EXTRACT(EPOCH'] as $ailleurs) {
            $this->assertStringNotContainsString($ailleurs, $written);
        }
    }

    #[DataProvider('engines')]
    public function test_it_accepts_only_what_the_suite_runs(string $driver, bool $expected): void
    {
        $this->assertSame($expected, DatabaseEngine::isSupported($driver));
    }

    /** @return list<array{string, bool}> */
    public static function engines(): array
    {
        return [
            ['mysql', true],
            ['mariadb', true],
            ['sqlite', false],
            ['pgsql', false],
            ['sqlsrv', false],
        ];
    }

    /**
     * Le refus nomme le pilote **trouvé**, pas seulement ceux qu'on attend.
     *
     * « Il faut MySQL » laisse le lecteur chercher où regarder, et la réponse
     * est presque toujours un `DB_CONNECTION` auquel personne n'a pensé.
     */
    public function test_the_refusal_names_the_engine_it_found(): void
    {
        $refusal = DatabaseEngine::refusal('sqlite');

        $this->assertStringContainsString('sqlite', $refusal);
        $this->assertStringContainsString('MySQL ou MariaDB', $refusal);
        $this->assertStringContainsString('DB_CONNECTION', $refusal);
    }
}
