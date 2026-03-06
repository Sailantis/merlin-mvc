<?php
// Simple benchmark harness for view engines
require_once __DIR__ . '/../vendor/autoload.php';

use Merlin\Mvc\Engines\Adapters\TwigAdapter;
use Merlin\Mvc\Engines\Adapters\PlatesAdapter;
use Merlin\Mvc\Engines\Adapters\BladeAdapter;
use Merlin\Mvc\Engines\ClarityEngine;
use Merlin\Mvc\Engines\NativeEngine;

$opts = getopt('', ['engines::', 'iterations::']);
$engines = isset($opts['engines']) ? explode(',', $opts['engines']) : ['clarity', 'native', 'twig', 'plates', 'blade'];
$iters = isset($opts['iterations']) ? (int) $opts['iterations'] : 1000;

$template = 'benchmarks::sample';
$viewPath = __DIR__ . '/templates';

function stats(array $values)
{
    sort($values);
    $count = count($values);
    $sum = array_sum($values);
    $mean = $sum / $count;
    $median = $values[(int) floor($count / 2)];
    $p95 = $values[(int) floor($count * 0.95)];
    return ['count' => $count, 'mean' => $mean, 'median' => $median, 'p95' => $p95];
}

foreach ($engines as $key) {
    $key = trim($key);
    echo "\n=== Engine: $key\n";
    switch ($key) {
        case 'clarity':
            $engine = new ClarityEngine();
            $engine->setExtension('clarity.html');
            break;
        case 'native':
            $engine = new NativeEngine();
            $engine->setExtension('php');
            break;
        case 'twig':
            $engine = new TwigAdapter();
            break;
        case 'plates':
            $engine = new PlatesAdapter();
            break;
        case 'blade':
            $engine = new BladeAdapter();
            break;
        default:
            echo "Unknown engine: $key\n";
            continue 2;
    }

    $engine->setViewPath($viewPath);
    $engine->addNamespace('benchmarks', $viewPath);

    // warm (compile) run
    echo "Warm run...\n";
    $start = hrtime(true);
    try {
        $out = $engine->render($template, ['title' => 'Benchmark', 'items' => range(1, 50)]);
    } catch (Exception $e) {
        echo "Error during warm run: " . $e->getMessage() . "\n";
        continue;
    }
    $warmMs = (hrtime(true) - $start) / 1e6;
    $warmMem = memory_get_peak_usage(true);
    echo sprintf("Warm: %.3f ms, peak mem: %d bytes\n", $warmMs, $warmMem);

    // iterative measurement
    $times = [];
    $peakMem = 0;
    for ($i = 0; $i < $iters; $i++) {
        $t0 = hrtime(true);
        $engine->render($template, ['title' => 'Benchmark', 'items' => range(1, 50)]);
        $t1 = hrtime(true);
        $times[] = ($t1 - $t0) / 1e6; // ms
        $peakMem = max($peakMem, memory_get_peak_usage(true));
    }

    $s = stats($times);
    echo sprintf(
        "Iterations: %d — mean: %.3f ms, median: %.3f ms, p95: %.3f ms, peak mem: %d bytes\n",
        $s['count'],
        $s['mean'],
        $s['median'],
        $s['p95'],
        $peakMem
    );
}
