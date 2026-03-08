<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Merlin\Mvc\Engines\Adapters\TwigAdapter;
use Merlin\Mvc\Engines\Adapters\PlatesAdapter;
use Merlin\Mvc\Engines\Adapters\BladeAdapter;
use Merlin\Mvc\Engines\ClarityEngine;
use Merlin\Mvc\Engines\NativeEngine;

$engines = [
    'clarity' => function () {
        $e = new ClarityEngine();
        $e->setExtension('.clarity.html');
        return $e;
    },
    'native' => function () {
        $e = new NativeEngine();
        $e->setExtension('.native.php');
        return $e;
    },
    'twig' => function () {
        return new TwigAdapter();
    },
    'plates' => function () {
        return new PlatesAdapter();
    },
    'blade' => function () {
        return new BladeAdapter();
    },
];

$viewPath = __DIR__ . '/templates';
$template = 'benchmarks::sample';
$baseline = null;

foreach ($engines as $name => $factory) {
    echo "Checking $name... ";
    try {
        $e = $factory();
        $e->setViewPath($viewPath);
        $e->addNamespace('benchmarks', $viewPath);
        $out = $e->render($template, ['title' => 'Verify', 'items' => ['a', 'b', 'c']]);
        $norm = preg_replace('/\s+/', ' ', trim(strip_tags($out)));
        if ($baseline === null) {
            $baseline = $norm;
            echo "baseline set\n";
        } else {
            if ($norm === $baseline) {
                echo "OK\n";
            } else {
                echo "MISMATCH\n";
                echo "--- baseline (normalized) ---\n" . $baseline . "\n";
                echo "--- $name (normalized) ---\n" . $norm . "\n";
                echo "--- raw output ({$name}) ---\n" . $out . "\n";
                // Also attempt to show baseline raw by re-rendering the baseline engine
                // (best-effort, skip on errors)
                try {
                    $re = $engines[array_key_first($engines)]();
                    $re->setViewPath($viewPath);
                    $re->addNamespace('benchmarks', $viewPath);
                    $baselineRaw = $re->render($template, ['title' => 'Verify', 'items' => ['a', 'b', 'c']]);
                    echo "--- raw output (baseline engine) ---\n" . $baselineRaw . "\n";
                } catch (Exception $ex) {
                    // ignore
                }
            }
        }
    } catch (Exception $e) {
        echo "error: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
