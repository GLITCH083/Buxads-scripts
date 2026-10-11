<?php
/** FaucetPepe instant bot. @version 1.2.0 */
require_once dirname(__DIR__, 2) . '/functions/instant.php';
if (!defined('FP_LIBRARY_ONLY')) {
    try {
        fp_main($_SERVER['argv'] ?? [], ['host'=>'faucetpepe.xyz', 'title'=>'FaucetPepe', 'mode'=>'form', 'coins'=>['ltc']]);
    } catch (Throwable $e) { echo 'ERROR: ' . $e->getMessage() . PHP_EOL; }
}
