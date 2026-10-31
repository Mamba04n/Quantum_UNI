<?php

namespace App\Domain\Documentos\Pdf\Support;

class RfqTextTranslator
{
    private const EXACT_LINES = [
        'precio unitario y total.' => 'Unit and total price.',
        'precio unitario y total' => 'Unit and total price.',
        'moneda de la oferta.' => 'Offer currency.',
        'moneda de la oferta' => 'Offer currency.',
        'tiempo de entrega estimado.' => 'Estimated delivery time.',
        'tiempo de entrega estimado' => 'Estimated delivery time.',
        'condiciones de garantia.' => 'Warranty terms.',
        'condiciones de garantia' => 'Warranty terms.',
        'condiciones de garantía.' => 'Warranty terms.',
        'condiciones de garantía' => 'Warranty terms.',
        'forma de pago requerida.' => 'Required payment terms.',
        'forma de pago requerida' => 'Required payment terms.',
        'tiempo de validez de la oferta.' => 'Offer validity period.',
        'tiempo de validez de la oferta' => 'Offer validity period.',
    ];

    private const PHRASES = [
        'Favor responder esta solicitud con prioridad y confirmar disponibilidad por partida.' => 'Please respond to this request with priority and confirm availability by line item.',
        'Responder esta solicitud con prioridad y confirmar disponibilidad por partida.' => 'Please respond to this request with priority and confirm availability by line item.',
        'Entregar con prioridad.' => 'Handle with priority.',
        'Departamento de Compras' => 'Purchasing Department',
    ];

    public function toEnglish(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }

        $translatedLines = collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line): string => $this->translateLine($line))
            ->all();

        return implode("\n", $translatedLines);
    }

    private function translateLine(string $line): string
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return $line;
        }

        $normalized = mb_strtolower($trimmed);

        if (isset(self::EXACT_LINES[$normalized])) {
            return $this->preserveIndentation($line, self::EXACT_LINES[$normalized]);
        }

        $translated = $trimmed;

        foreach (self::PHRASES as $spanish => $english) {
            $translated = str_replace($spanish, $english, $translated);
        }

        return $this->preserveIndentation($line, $translated);
    }

    private function preserveIndentation(string $original, string $translated): string
    {
        preg_match('/^\s*/', $original, $matches);

        return ($matches[0] ?? '').$translated;
    }
}
