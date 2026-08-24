<?php

namespace App\Service;

/**
 * Découvre par réflexion les paires de champs "xxx" / "xxxEn" d'une entité
 * bilingue — accesseurs getXxx()/setXxx() + getXxxEn()/setXxxEn() attendus,
 * rien d'autre à déclarer côté entité. Partagé entre App\Service\
 * ContentAutoTranslator (traduction Claude au moment de l'enregistrement) et
 * App\Repository\TranslationRepository (stockage dans la table `translation`).
 */
final class BilingualFieldReflector
{
    /**
     * @return array<string, array{frGetter: \ReflectionMethod, enGetter: \ReflectionMethod, enSetter: \ReflectionMethod}>
     */
    public function discoverPairs(object $entity): array
    {
        $reflection = new \ReflectionClass($entity);
        $pairs = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (!str_starts_with($name, 'get') || !str_ends_with($name, 'En') || 'get' === $name) {
                continue;
            }
            if (0 !== $method->getNumberOfRequiredParameters()) {
                continue;
            }

            $base = substr($name, 3, -2); // "getHeroSubEn" -> "HeroSub"
            if ('' === $base) {
                continue;
            }
            $base = lcfirst($base);

            $frGetterName = 'get'.ucfirst($base);
            $enSetterName = 'set'.ucfirst($base).'En';
            if (!$reflection->hasMethod($frGetterName) || !$reflection->hasMethod($enSetterName)) {
                continue;
            }

            $pairs[$base] = [
                'frGetter' => $reflection->getMethod($frGetterName),
                'enGetter' => $method,
                'enSetter' => $reflection->getMethod($enSetterName),
            ];
        }

        return $pairs;
    }

    /** Vrai si la paire attend un tableau (ex. heroRoles) plutôt qu'une simple chaîne. */
    public function isArrayField(\ReflectionMethod $enSetter): bool
    {
        $params = $enSetter->getParameters();
        $type = $params[0]->getType();

        return $type instanceof \ReflectionNamedType && 'array' === $type->getName();
    }

    public function isBlank(mixed $value): bool
    {
        if (null === $value) {
            return true;
        }
        if (is_string($value)) {
            return '' === trim($value);
        }
        if (is_array($value)) {
            return [] === $value;
        }

        return false;
    }
}
