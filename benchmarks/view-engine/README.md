Benchmark harness for view engines

Usage:

1. Install dev dependencies:

```bash
composer install --dev
```

2. Verify functional parity across engines:

```bash
php benchmarks/verify.php
```

3. Run benchmark (default 1000 iterations):

```bash
php -d opcache.enable_cli=1 benchmarks/run.php --engines=native,clarity,twig,plates,blade --iterations=10000 --out=results-`date +%Y-%m-%d-%H%M%S`

# Or run each engine separately to generate individual reports.
for e in native clarity twig plates blade; do
  php -d opcache.enable_cli=1 benchmarks/view-engine/run.php \
    --engines="$e" \
    --iterations=10000 \
    --out="$e-$(date +%Y-%m-%d-%H%M%S)"
done
```

Notes:

- Adapters will throw if a dependency is missing; add packages to composer first.
- Results are printed to stdout; you can adapt `benchmarks/run.php` to emit CSV/JSON.
