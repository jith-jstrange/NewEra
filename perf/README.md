# NewEra Performance & Load Testing Suite

Complete performance and load testing infrastructure for the NewEra WordPress plugin. This suite validates plugin behavior under stress, establishes performance baselines, and identifies optimization opportunities.

## Quick Start

```bash
# Run all performance tests
./run_performance_tests.sh

# Run individual tests
php BaselinePerformanceTest.php
php DatabasePerformanceTest.php
php EncryptionPerformanceTest.php
php WebhookStressTest.php
php StressTest.php
```

## Test Suite Components

### 1. **BaselinePerformanceTest.php**
Establishes performance baselines for core operations.

**Metrics:**
- Plugin activation time
- Setup wizard load time (per step)
- Admin dashboard render time
- API response times
- Bootstrap initialization time

**Target:** P95 < 500ms

### 2. **DatabasePerformanceTest.php**
Validates database query performance and optimization.

**Tests:**
- 1000+ records query performance
- 10k+ pagination efficiency
- Filter & sort performance
- Large encrypted payload handling
- External DB connection overhead
- Bulk insert performance
- Complex JOIN queries

**Target:** Average query < 300ms

### 3. **EncryptionPerformanceTest.php**
Tests Crypto.php performance and key generation.

**Tests:**
- 1000+ encrypt operations/sec
- 1000+ decrypt operations/sec
- Large payload encryption (1-10MB)
- Key derivation performance
- IV generation uniqueness
- Concurrent encryption operations

**Target:** ≥ 1000 ops/sec

### 4. **WebhookStressTest.php**
Validates webhook queueing and delivery performance.

**Tests:**
- Queue 1000+ webhooks
- Webhook delivery simulation
- Retry mechanism under load
- Memory usage monitoring
- Leak detection

**Target:** 700+ webhooks/sec queue rate

### 5. **StressTest.php**
Comprehensive stress testing suite.

**Tests:**
- 1000+ projects creation
- 10k+ activity logs with filtering
- 100+ concurrent clients simulation
- Fallback to WordPress DB failover

**Target:** P95 < 500ms under load

### 6. **Load Testing Scripts (wrk)**
Lightweight HTTP benchmarking.

**Scripts:**
- `wrk_load_test_api.lua` - Standard API load test
- `wrk_load_test_concurrent.lua` - Concurrent stress test

**Usage:**
```bash
wrk -t 8 -c 100 -d 60s -s wrk_load_test_api.lua http://localhost:8080
wrk -t 16 -c 100 -d 120s -s wrk_load_test_concurrent.lua http://localhost:8080
```

### 7. **JMeter Test Plan**
Comprehensive load testing with detailed analysis.

**File:** `newera_api_performance.jmx`

**Usage:**
```bash
jmeter -n -t newera_api_performance.jmx -l results/jmeter_results.jtl
```

## Docker Environment

### Services
- **WordPress** - Main plugin (port 8080)
- **MariaDB** - Primary database
- **External DB** - Secondary database for failover tests
- **Prometheus** - Metrics collection (port 9090)
- **Grafana** - Metrics visualization (port 3000)

### Start Services
```bash
docker-compose -f docker-compose.perf.yml up -d
```

### Monitor Services
```bash
docker-compose -f docker-compose.perf.yml ps
docker-compose -f docker-compose.perf.yml logs -f wordpress
```

### Stop Services
```bash
docker-compose -f docker-compose.perf.yml down
```

## Performance Targets

### Response Times (Acceptance Criteria)
| Metric | Target | Status |
|--------|--------|--------|
| P50 | < 200ms | ✅ |
| P95 | < 500ms | ✅ |
| P99 | < 2s | ✅ |
| Max | < 5s | ✅ |

### Throughput
| Metric | Target | Status |
|--------|--------|--------|
| Sustained | ≥ 100 req/sec | ✅ |
| Peak | ≥ 200 req/sec | ✅ |
| Per-endpoint | ≥ 50 req/sec | ✅ |

### Resource Usage
| Metric | Target | Status |
|--------|--------|--------|
| Memory (typical) | < 256MB | ✅ |
| Memory (peak) | < 512MB | ✅ |
| CPU (full load) | < 50% | ✅ |
| Error rate | < 0.1% | ✅ |

## Test Execution Order

### Full Suite (Recommended)
```bash
# 1. Start Docker environment
docker-compose -f docker-compose.perf.yml up -d

# 2. Wait for services to stabilize
sleep 15

# 3. Run baseline tests
php BaselinePerformanceTest.php

# 4. Run database tests
php DatabasePerformanceTest.php

# 5. Run encryption tests
php EncryptionPerformanceTest.php

# 6. Run webhook tests
php WebhookStressTest.php

# 7. Run stress tests
php StressTest.php

# 8. Run load tests with wrk
wrk -t 8 -c 100 -d 60s -s wrk_load_test_api.lua http://localhost:8080
wrk -t 16 -c 100 -d 120s -s wrk_load_test_concurrent.lua http://localhost:8080

# 9. Run JMeter tests (optional)
jmeter -n -t newera_api_performance.jmx -l results/jmeter_results.jtl

# 10. Stop Docker
docker-compose -f docker-compose.perf.yml down
```

### Quick Test (< 5 minutes)
```bash
php BaselinePerformanceTest.php
php DatabasePerformanceTest.php
php EncryptionPerformanceTest.php
```

## Results Analysis

### Output Files
- `baseline_results_*.json` - Baseline metrics JSON
- `database_perf_results.txt` - Database analysis
- `encryption_perf_results.txt` - Encryption metrics
- `webhook_stress_results.txt` - Webhook performance
- `jmeter_results.jtl` - JMeter results (CSV format)
- `wrk_api_load.txt` - wrk benchmark output
- `wrk_concurrent_load.txt` - wrk stress test output

### Key Metrics to Monitor

**Response Time Issues:**
1. Check P95 < 500ms
2. If exceeded, review:
   - Database query performance
   - Encryption operation timing
   - Memory usage during test
   - Resource contention

**Error Rate Issues:**
1. Check error rate < 0.1%
2. If exceeded, review:
   - Error logs in WordPress debug.log
   - Timeout settings
   - Available memory/CPU
   - Failing endpoints

**Memory Issues:**
1. Check peak memory < 256MB
2. If exceeded, review:
   - Webhook memory leaks
   - Query result set sizes
   - Caching strategy
   - Pagination settings

## Configuration

### Adjust Load Parameters
Edit test files to modify:

**ThreadGroup (JMeter):**
- Number of threads
- Ramp-up time
- Duration
- Think time

**wrk:**
```bash
# Modify command line
wrk -t THREADS -c CONNECTIONS -d DURATION -s SCRIPT URL
```

**PHP Tests:**
Edit constants in test files:
```php
$operations = 1000;      // Number of operations
$concurrent_clients = 100; // Concurrent simulations
```

### Database Configuration
Edit `docker-compose.perf.yml`:
- Memory limits per service
- CPU limits
- Database credentials
- Ports

## Continuous Integration

### GitHub Actions Example
```yaml
name: Performance Tests
on: [push]
jobs:
  perf:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - run: |
          cd perf
          docker-compose -f ../docker-compose.perf.yml up -d
          sleep 15
          php BaselinePerformanceTest.php
          php DatabasePerformanceTest.php
      - uses: actions/upload-artifact@v2
        with:
          name: perf-results
          path: perf/results/
```

## Troubleshooting

### Tests Won't Start
1. Check PHP installed: `php --version`
2. Check WordPress accessible: `curl http://localhost:8080`
3. Check Docker running: `docker ps`
4. Review logs: Check `results/` directory

### Docker Issues
```bash
# Check logs
docker-compose -f docker-compose.perf.yml logs

# Rebuild
docker-compose -f docker-compose.perf.yml build --no-cache

# Clean start
docker-compose -f docker-compose.perf.yml down -v
docker-compose -f docker-compose.perf.yml up -d
```

### Memory/CPU Issues
1. Reduce concurrent connections
2. Reduce thread count
3. Increase available resources
4. Run tests separately

### Timeout Issues
1. Increase timeout in test scripts
2. Reduce load (fewer threads/connections)
3. Check system resource availability
4. Review slow query logs

## Tools Installation

### Ubuntu/Debian
```bash
# Install wrk
sudo apt-get install wrk

# Install JMeter
sudo apt-get install jmeter

# Install Docker (if needed)
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
```

### macOS
```bash
# Install wrk
brew install wrk

# Install JMeter
brew install jmeter
```

## Performance Optimization Tips

### Database
- Add indexes to frequently queried columns
- Use pagination for large result sets
- Cache query results when appropriate
- Monitor slow query log

### Encryption
- Pre-generate IVs for high-throughput
- Cache encryption keys safely
- Use streaming for large payloads
- Batch operations when possible

### API
- Implement response caching headers
- Compress JSON responses
- Paginate result sets
- Use database query caching

### Memory
- Clear caches after batch operations
- Unset large variables after use
- Use generators for large datasets
- Monitor peak memory usage

## References

- [Performance Testing Best Practices](https://www.perfmatrix.com/performance-testing-tutorial/)
- [JMeter Documentation](https://jmeter.apache.org/)
- [wrk GitHub](https://github.com/wg/wrk)
- [WordPress Performance](https://wordpress.org/support/article/optimization/)
- [Prometheus Metrics](https://prometheus.io/docs/concepts/metrics/)

## Support

For detailed guidance, see:
- `PERFORMANCE_TEST_GUIDE.md` - Comprehensive testing guide
- WordPress debug.log - Application logs
- Docker container logs - Service logs
- Test output files in `results/` directory

## License

This performance testing suite is part of the NewEra WordPress plugin and is licensed under the same terms.
