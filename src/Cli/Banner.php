<?php

declare(strict_types=1);

namespace Next\Cli;

/**
 * Synthwave Sunset Retro Banner & CLI Icon for NEXT.php.
 *
 * Replaces traditional Vercel triangle with the iconic synthwave horizontal striped sunset.
 */
class Banner
{
    /**
     * Render the full retro synthwave striped sunset ASCII banner.
     */
    public static function render(): string
    {
        $y1 = "\033[38;2;255;238;88m"; // light yellow
        $y2 = "\033[38;2;255;213;79m"; // yellow
        $o1 = "\033[38;2;255;167;38m"; // orange
        $o2 = "\033[38;2;255;112;67m"; // dark orange
        $p  = "\033[38;2;233;30;99m";  // pink
        $m  = "\033[38;2;171;71;188m"; // purple
        $c  = "\033[38;2;0;229;255m";   // cyan horizon
        $rst= "\033[0m";

        return <<<BANNER

      {$y1}▄▄██████▄▄{$rst}          ███╗   ██╗███████╗██╗  ██╗████████╗    ██████╗ ██╗  ██╗██████╗ 
    {$y2}▄████████████▄{$rst}        ████╗  ██║██╔════╝╚██╗██╔╝╚══██╔══╝    ██╔══██╗██║  ██║██╔══██╗
  {$o1}██████████████████{$rst}      ██╔██╗ ██║█████╗   ╚███╔╝    ██║       ██████╔╝███████║██████╔╝
  {$o2}▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀{$rst}      ██║╚██╗██║██╔══╝   ██╔██╗    ██║       ██╔═══╝ ██╔══██║██╔═══╝ 
    {$p}▄▄▄▄▄▄▄▄▄▄▄▄▄▄{$rst}        ██║ ╚████║███████╗██╔╝ ██╗   ██║   ██╗ ██║     ██║  ██║██║     
      {$m}▄▄██████▄▄{$rst}          ╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═╝     ╚═╝  ╚═╝╚═╝     
{$c}═════════════════════════════════════════════════════════════════════════════════════════{$rst}

BANNER;
    }

    /**
     * Single-line synthwave sunset sun icon replacing the Vercel triangle.
     */
    public static function icon(): string
    {
        return "\033[38;2;255;167;38m☼\033[0m";
    }

    /**
     * Formatted header line: "[icon] NEXT.php [version]".
     */
    public static function header(string $version = '1.0.0'): string
    {
        return sprintf("   %s \033[1;37mNEXT.php\033[0m %s\n", self::icon(), $version);
    }
}
