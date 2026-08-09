<?php

namespace App\Service;

use DeviceDetector\DeviceDetector;

/**
 * Traduit un User-Agent brut (stocké tel quel dans User::lastDevice /
 * LoginHistory::device, cf. LoginListener) en type d'appareil + marque
 * lisibles. S'appuie sur matomo/device-detector, déjà une dépendance du
 * projet mais jamais utilisée jusqu'ici — le User-Agent brut était stocké
 * sans aucun parsing.
 */
class DeviceParser
{
    private const TYPE_LABELS = [
        'desktop' => 'Ordinateur',
        'smartphone' => 'Smartphone',
        'tablet' => 'Tablette',
        'phablet' => 'Phablette',
        'feature phone' => 'Téléphone basique',
        'console' => 'Console de jeu',
        'tv' => 'Télévision',
        'car browser' => 'Véhicule connecté',
        'smart display' => 'Écran connecté',
        'camera' => 'Appareil photo',
        'portable media player' => 'Lecteur multimédia',
        'smart speaker' => 'Enceinte connectée',
        'wearable' => 'Objet connecté',
        'peripheral' => 'Périphérique',
    ];

    /**
     * @return array{type: string, brand: ?string, model: ?string, os: ?string, browser: ?string, label: string, isBot: bool}
     */
    public function parse(?string $userAgent): array
    {
        if (null === $userAgent || '' === trim($userAgent)) {
            return $this->unknown();
        }

        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        if ($detector->isBot()) {
            $bot = $detector->getBot();

            return [
                'type' => 'Robot',
                'brand' => null,
                'model' => null,
                'os' => null,
                'browser' => null,
                'label' => 'Robot' . (!empty($bot['name']) ? ' (' . $bot['name'] . ')' : ''),
                'isBot' => true,
            ];
        }

        $typeKey = $detector->getDeviceName();
        $type = self::TYPE_LABELS[$typeKey] ?? 'Appareil inconnu';
        $brand = $detector->getBrandName() ?: null;
        $model = $detector->getModel() ?: null;
        $os = $detector->getOs('name') ?: null;
        $browser = $detector->getClient('name') ?: null;

        return [
            'type' => $type,
            'brand' => $brand,
            'model' => $model,
            'os' => $os,
            'browser' => $browser,
            'label' => implode(' · ', array_filter([$model ?: $brand ?: $type, $browser, $os])),
            'isBot' => false,
        ];
    }

    /**
     * @return array{type: string, brand: ?string, model: ?string, os: ?string, browser: ?string, label: string, isBot: bool}
     */
    private function unknown(): array
    {
        return [
            'type' => 'Inconnu',
            'brand' => null,
            'model' => null,
            'os' => null,
            'browser' => null,
            'label' => 'Appareil inconnu',
            'isBot' => false,
        ];
    }
}
