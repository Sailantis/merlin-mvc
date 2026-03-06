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
php benchmarks/run.php --engines=clarity,native,twig,plates,blade --iterations=1000
```

Notes:

- Adapters will throw if a dependency is missing; add packages to composer first.
- Results are printed to stdout; you can adapt `benchmarks/run.php` to emit CSV/JSON.
