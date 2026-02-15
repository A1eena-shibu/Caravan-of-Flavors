<?php
/**
 * Currency Service
 * Handles live exchange rates and price conversion
 */

class CurrencyService
{
    private static $apiUrl = "https://open.er-api.com/v6/latest/"; // Base URL
    const BASE_CURRENCY = 'INR';

    public static function getExchangeRates($baseCurrency = self::BASE_CURRENCY)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cacheKey = "rates_" . $baseCurrency;

        // Cache in session for 1 hour
        if (isset($_SESSION[$cacheKey]) && (time() - $_SESSION[$cacheKey . '_time'] < 3600)) {
            return $_SESSION[$cacheKey];
        }

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::$apiUrl . $baseCurrency);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }

            curl_close($ch);

            $data = json_decode($response, true);

            if (!$data || $data['result'] !== 'success') {
                throw new Exception("API failed to return rates");
            }

            $_SESSION[$cacheKey] = $data['rates'];
            $_SESSION[$cacheKey . '_time'] = time();

            return $data['rates'];
        } catch (Exception $e) {
            error_log("CurrencyService Error: " . $e->getMessage());
            // Fallback manual rates or return empty if system really needs live data
            return [];
        }
    }

    public static function convert($amount, $fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency)
            return $amount;

        $rates = self::getExchangeRates($fromCurrency);

        if (empty($rates) || !isset($rates[$toCurrency])) {
            // Fallback: If fromCurrency rates fail, try USD as base
            $usdRates = self::getExchangeRates('USD');
            if (!empty($usdRates) && isset($usdRates[$fromCurrency]) && isset($usdRates[$toCurrency])) {
                $amountInUsd = $amount / $usdRates[$fromCurrency];
                return $amountInUsd * $usdRates[$toCurrency];
            }
            return $amount; // Default to no conversion if all fails
        }

        return $amount * $rates[$toCurrency];
    }

    public static function formatPrice($amount, $currencySymbol, $currencyCode)
    {
        return $currencySymbol . " " . number_format($amount, 2) . " " . $currencyCode;
    }
}
?>