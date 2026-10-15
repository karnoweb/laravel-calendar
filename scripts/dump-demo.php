<?php
$p = new PDO('sqlite:'.__DIR__.'/../workbench/database/demo.sqlite');
foreach ($p->query('select title,scope_key,starts_at from demo_tasks order by starts_at') as $r) {
    echo $r['title'].' | '.$r['scope_key'].' | '.$r['starts_at'].PHP_EOL;
}
echo "---\n";
foreach ($p->query('select title,scope_key,starts_at from demo_recurring_series') as $r) {
    echo $r['title'].' | '.$r['scope_key'].' | '.$r['starts_at'].PHP_EOL;
}
