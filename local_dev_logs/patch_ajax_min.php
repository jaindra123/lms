<?php
$p = dirname(__DIR__) . '/lib/amd/build/ajax.min.js';
$j = file_get_contents($p);
$old = 's?j+=m+"?info="+v:(j+=(m="service-nologin.php")+"?info="+v,f&&(j+="&cachekey="+f,y.type="GET"))';
$new = 's?j+=m+"?info="+v:(j+=(m="service-nologin.php")+"?info="+v)';
if (!str_contains($j, $old)) {
    fwrite(STDERR, "pattern not found\n");
    exit(1);
}
file_put_contents($p, str_replace($old, $new, $j));
echo "ok\n";
