<?php

namespace App\Domain\Administracion\Company\Services;

class CompanyProfileService
{
    public const COMPANY_QUANTUM = 'quantum';
    public const COMPANY_POWERSALES = 'powersales';
    public const COMPANY_MARCOS_LEIVA = 'marcos_leiva';

    /**
     * Lista de empresas emisoras configuradas para ofertas y facturación.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCompanies(): array
    {
        return [
            self::COMPANY_QUANTUM => [
                'key' => self::COMPANY_QUANTUM,
                'name' => config('company.name', 'Quantum Soluciones S.A.'),
                'short_name' => 'Quantum Soluciones',
                'legal_name' => config('company.legal_name', 'Quantum Soluciones S.A.'),
                'slogan' => config('company.slogan', 'Repuestos - Servicio - Maquinaria'),
                'address' => config('company.address', 'Rotonda Jean Paul Genie 100m al Este, 50m al Sur, Edificio Quantum, Managua'),
                'email' => config('company.email', 'ventas@quantumsolucionesnic.com'),
                'phone' => config('company.phone', '+505 7669 8164'),
                'website' => config('company.website', 'quantumsolucionesnic.com'),
                'ruc' => config('company.ruc', 'J0310000475024'),
                'logo_path' => public_path('images/logo-email.png'),
                'schedule' => config('company.schedule', 'Lunes a Viernes 8:00 AM - 5:00 PM'),
                'color_primary' => '#A00F0F',
                'color_secondary' => '#8F0000',
                'badge_classes' => 'bg-slate-900 text-white border-slate-700',
                'badge_light' => 'bg-rose-50 text-rose-800 border-rose-200',
                'cheque_to' => 'Quantum Soluciones S.A.',
                'bank_accounts' => [],
            ],
            self::COMPANY_POWERSALES => [
                'key' => self::COMPANY_POWERSALES,
                'name' => 'Power Sales',
                'short_name' => 'Power Sales',
                'legal_name' => 'POWER SALES',
                'slogan' => 'THE BEST OPTION',
                'address' => 'Colonia centroamerica, de los Semaforos 1c al N. 1c al E.',
                'email' => 'Ventas02@powersalesnic.com',
                'email_alt' => 'Powersales.products@outlook.com',
                'phone' => '+505 8326-2783',
                'phone_alt' => '+505 8456-7408',
                'website' => 'Powersalesnic.com',
                'ruc' => '2831402011000W',
                'logo_path' => public_path('images/companies/powersales.png'),
                'schedule' => 'Lunes a Viernes 8:00 AM - 5:00 PM',
                'color_primary' => '#0B2545',
                'color_secondary' => '#0369A1',
                'badge_classes' => 'bg-blue-900 text-white border-blue-800',
                'badge_light' => 'bg-blue-50 text-blue-800 border-blue-200',
                'cheque_to' => 'Katya Mirbania Leiva Tórrez',
                'bank_accounts' => [
                    'dolares' => 'Cuenta BAC dólares: 371001983',
                    'cordobas' => 'Cuenta BAC Córdobas: 372092205',
                ],
            ],
            self::COMPANY_MARCOS_LEIVA => [
                'key' => self::COMPANY_MARCOS_LEIVA,
                'name' => 'Marcos Cecilio Leiva Ordoñez',
                'short_name' => 'Marcos Leiva',
                'legal_name' => 'Marcos Cecilio Leiva Ordoñez',
                'slogan' => 'Repuestos y suministros para mantenimiento',
                'address' => 'Donde fue el bar canducha 1C. al sur, Managua, Nicaragua',
                'email' => '73marcosleiva@gmail.com',
                'phone' => '+505 77955637',
                'website' => '',
                'ruc' => '0052211730002M',
                'logo_path' => public_path('images/companies/marcos_leiva.png'),
                'schedule' => 'Lunes a Viernes 8:00 AM - 5:00 PM',
                'color_primary' => '#2D472C',
                'color_secondary' => '#8B1E1E',
                'badge_classes' => 'bg-emerald-900 text-white border-emerald-800',
                'badge_light' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'cheque_to' => 'MARCOS CECILIO LEIVA ORDOÑEZ',
                'bank_accounts' => [
                    'cordobas' => '374120822 BAC CORDOBAS - MARCOS CECILIO LEIVA ORDOÑEZ',
                ],
            ],
        ];
    }

    /**
     * Obtiene los datos de una empresa específica o de la empresa por defecto.
     */
    public function getCompanyData(?string $companyKey = null): array
    {
        $companies = $this->getCompanies();
        $key = $companyKey ?: self::COMPANY_QUANTUM;

        return $companies[$key] ?? $companies[self::COMPANY_QUANTUM];
    }

    /**
     * Obtiene datos formateados para el pie de documento.
     */
    public function getFooterData(?string $companyKey = null): array
    {
        $company = $this->getCompanyData($companyKey);

        return [
            'address' => $company['address'] ?? 'Rotonda Jean Paul Genie',
            'contact' => ($company['phone'] ?? '').' | '.($company['email'] ?? ''),
            'schedule' => $company['schedule'] ?? 'Lunes a Viernes 8:00 AM - 5:00 PM',
            'system' => 'Generado por Sistema ERP - '.$company['name'],
        ];
    }
}
