<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Renaming\Rector\Name\RenameClassRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/AdminBundle.php',
        __DIR__.'/Appearance',
        __DIR__.'/Contract',
        __DIR__.'/Controller',
        __DIR__.'/DependencyInjection',
        __DIR__.'/Entity',
        __DIR__.'/Form',
        __DIR__.'/Navigation',
        __DIR__.'/Tests',
        __DIR__.'/Twig',
        __DIR__.'/Ui',
    ])
    // No argument: the target PHP version is read from the "php" constraint in
    // composer.json, so the rule set follows the bundle instead of drifting.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withComposerBased(doctrine: true, symfony: true, phpunit: true)
    ->withSkip([
        // ⚠️ `eraseCredentials()` de la fixture n'est appelée par personne et a quitté
        // `UserInterface` en Symfony 8 : Rector la voit morte et la retire. Elle ne l'est pas sur
        // la branche 7.4, où l'interface la déclare encore — sans elle, la classe y est abstraite.
        // Un bundle qui promet `^7.4 || ^8.0` doit tenir les deux.
        // ⚠️ `RemoveEraseCredentialsRector` retire `eraseCredentials()` parce que Symfony 8 l'a
        // sortie de `UserInterface`. Vrai sur cette branche, faux sur la 7.4, où l'interface la
        // déclare encore : sans la méthode, la classe y est ABSTRAITE et le chargement est fatal.
        // Un bundle qui promet `^7.4 || ^8.0` doit tenir les deux, et Rector ne raisonne que sur
        // la version installée localement — c'est le jeu `lowest deps` de la CI qui l'a montré.
        Rector\Symfony\Symfony80\Rector\Class_\RemoveEraseCredentialsRector::class,
        // Ce déplacement de namespace vise `Symfony\Component\DependencyInjection\Kernel\BundleInterface`,
        // qui n'existe pas en Symfony 8.1 — et le bundle déclare `^7.4 || ^8.0`, donc il ne peut
        // pas s'appuyer sur une classe présente d'un seul côté. `HttpKernel\Bundle\BundleInterface`
        // existe sur les deux branches : c'est celle-là qu'on garde.
        RenameClassRector::class => [
            __DIR__.'/Tests/Fixtures/TestKernel.php',
        ],
        // Pure helpers are deliberately static: it documents that they touch no state.
        LocallyCalledStaticMethodToNonStaticRector::class,
        // Doctrine entities keep their mapped properties out of the constructor, so
        // the test fixtures stay representative of real consumer code.
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__.'/Tests/Fixtures/Entity',
        ],
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
