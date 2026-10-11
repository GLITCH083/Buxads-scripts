<?php
/** ClaimPepe instant bot. @version 1.2.0 */
require_once dirname(__DIR__, 2) . '/functions/instant.php';
if (!defined('FP_LIBRARY_ONLY')) {
    try {
        fp_main($_SERVER['argv'] ?? [], ['host'=>'claimpepe.xyz', 'title'=>'ClaimPepe', 'mode'=>'form', 'coins'=>['ltc','usdt','dgb','sol','fey','pepe','doge']]);
    } catch (Throwable $e) { echo 'ERROR: ' . $e->getMessage() . PHP_EOL; }
}
