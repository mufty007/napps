<?php

namespace Modules\Community\Support;

use Illuminate\Support\HtmlString;

/**
 * Member-written text for display: escaped first, then web links made
 * clickable (opened safely in a new tab) and line breaks kept.
 */
final class RichText
{
    public static function render(?string $text): HtmlString
    {
        $html = e((string) $text);

        $html = (string) preg_replace_callback(
            '~\bhttps?://[^\s<]+[^\s<.,;:!?)\]\'"]~i',
            fn (array $m) => '<a href="'.$m[0].'" target="_blank" rel="nofollow noopener noreferrer" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 hover:text-brand-800 break-all">'.$m[0].'</a>',
            $html,
        );

        return new HtmlString(nl2br($html, false));
    }
}
