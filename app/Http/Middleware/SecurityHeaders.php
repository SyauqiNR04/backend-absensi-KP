<?php
/*
|==================================================================
| FITUR: Security Headers
| Menyisipkan header keamanan OWASP (nosniff, DENY frame, CSP, HSTS) dan menghapus header pembocor stack pada setiap respons API.
|==================================================================
*/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders
 * -------------------------------------------------------------------------
 * Menambahkan security response headers sesuai rekomendasi OWASP Secure
 * Headers Project. Untuk REST API, header terpenting adalah anti-sniffing
 * dan anti-clickjacking; CSP restriktif mencegah eksekusi bila endpoint
 * tak sengaja mengembalikan HTML.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Cegah MIME sniffing -> mempersempit XSS via response yang salah tipe.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Cegah clickjacking (API tidak perlu di-embed dalam frame).
        $response->headers->set('X-Frame-Options', 'DENY');

        // Batasi kebocoran URL internal via header Referer.
        $response->headers->set('Referrer-Policy', 'no-referrer');

        // Matikan fitur browser yang tak dibutuhkan API.
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // CSP restriktif: default menolak semua sumber daya aktif.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        // Paksa HTTPS pada koneksi berikutnya (aktif hanya via HTTPS).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Jangan bocorkan stack teknologi.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
