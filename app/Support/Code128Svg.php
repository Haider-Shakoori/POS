<?php

namespace App\Support;

use InvalidArgumentException;

class Code128Svg
{
    private const PATTERNS = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112',
    ];

    public function supports(string $value): bool
    {
        return $value !== '' && strlen($value) <= 80 && preg_match('/^[\x20-\x7E]+$/', $value) === 1;
    }

    public function render(string $value, int $moduleWidth = 2, int $barHeight = 54): string
    {
        if (! $this->supports($value)) {
            throw new InvalidArgumentException('CODE128 labels support 1-80 printable ASCII characters.');
        }

        $codes = [104];
        $checksum = 104;

        foreach (str_split($value) as $position => $character) {
            $code = ord($character) - 32;
            $codes[] = $code;
            $checksum += $code * ($position + 1);
        }

        $codes[] = $checksum % 103;
        $codes[] = 106;

        $quietModules = 10;
        $x = $quietModules;
        $bars = '';

        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code];
            foreach (str_split($pattern) as $index => $width) {
                $modules = (int) $width;
                if ($index % 2 === 0) {
                    $bars .= '<rect x="'.($x * $moduleWidth).'" y="0" width="'.($modules * $moduleWidth).'" height="'.$barHeight.'" fill="currentColor"/>';
                }
                $x += $modules;
            }
        }

        $totalWidth = ($x + $quietModules) * $moduleWidth;
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Barcode '.$escaped.'" viewBox="0 0 '.$totalWidth.' '.($barHeight + 18).'" width="100%" height="72" preserveAspectRatio="xMidYMid meet">'
            .$bars
            .'<text x="'.($totalWidth / 2).'" y="'.($barHeight + 14).'" text-anchor="middle" font-family="ui-monospace, monospace" font-size="11" fill="currentColor">'.$escaped.'</text>'
            .'</svg>';
    }
}
