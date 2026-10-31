<?php

namespace GiveTwice\ProductInfoFetcher\Detection;

use GiveTwice\ProductInfoFetcher\Enum\FetchOutcome;

class PageOutcomeDetector
{
    public function detect(string $html, ?int $status = null, ?string $url = null): ?FetchOutcome
    {
        if (in_array($status, [404, 410], true)) {
            return FetchOutcome::Removed;
        }

        // Only inspect visible text and specific challenge controls, not scripts or
        // footer links to privacy/cookie policies on otherwise usable product pages.
        $visible = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/si', '', $html);
        $text = html_entity_decode(strip_tags($visible), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/validateCaptcha|challenge-platform|cf-chl-|id=["\']challenge-form/i', $html)
            || preg_match('/captcha|ip address.{0,60}(is blocked|temporarily blocked)|access denied|robot check|verify (that )?you are human|unusual traffic|automated access|pardon our interruption|toegang geweigerd/i', $text)
            || in_array($status, [401, 403, 429], true)) {
            return FetchOutcome::Blocked;
        }

        if (preg_match('/sorry.{0,40}(page.{0,20}(not found|couldn.t find)|couldn.t find that page)|pagina (bestaat niet|niet gevonden)|page introuvable|seite nicht gefunden/iu', $text)) {
            return FetchOutcome::Removed;
        }

        if (preg_match('/consent\.(amazon|bol)\.|\/consent(?:[\/?]|$)/i', $url ?? '')
            || preg_match('/before you continue|voordat je (verder|doorgaat)|avant de continuer|bevor sie fortfahren|accepteer.{0,35}cookies om|accept.{0,35}cookies to continue/iu', $text)
            || preg_match('/<(form|div|section)[^>]*(id|class)=["\'][^"\']*(consent-wall|cookie-wall|consent-page)[^"\']*["\']/i', $html)) {
            return FetchOutcome::Consent;
        }

        if ($status !== null && $status >= 400) {
            return FetchOutcome::Error;
        }

        return null;
    }
}
