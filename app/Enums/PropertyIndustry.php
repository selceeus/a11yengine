<?php

namespace App\Enums;

enum PropertyIndustry: string
{
    case Retail = 'retail';
    case Finance = 'finance';
    case Healthcare = 'healthcare';
    case Hospitality = 'hospitality';
    case Education = 'education';
    case Government = 'government';
    case Entertainment = 'entertainment';
    case RealEstate = 'real_estate';
    case Travel = 'travel';
    case Technology = 'technology';
    case NonProfit = 'non_profit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail',
            self::Finance => 'Finance',
            self::Healthcare => 'Healthcare',
            self::Hospitality => 'Hospitality',
            self::Education => 'Education',
            self::Government => 'Government',
            self::Entertainment => 'Entertainment',
            self::RealEstate => 'Real Estate',
            self::Travel => 'Travel',
            self::Technology => 'Technology',
            self::NonProfit => 'Non-Profit',
            self::Other => 'Other',
        };
    }

    /**
     * Legal risk level based on ADA lawsuit exposure for this industry.
     *
     * @return 'high'|'medium'|'low'
     */
    public function legalRiskLevel(): string
    {
        return match ($this) {
            self::Retail, self::Finance, self::Healthcare, self::Hospitality => 'high',
            self::Education, self::Government, self::Travel, self::Entertainment => 'medium',
            self::Technology, self::RealEstate, self::NonProfit, self::Other => 'low',
        };
    }
}
