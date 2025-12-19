# NewEra Performance & Load Testing Guide

## Overview

This guide provides comprehensive documentation for running performance and load tests on the NewEra WordPress plugin. The test suite validates plugin behavior under stress and establishes performance baselines.

## Prerequisites

### Required Tools
- PHP 7.4+ (for PHP-based tests)
- Docker & Docker Compose (for containerized environment)
- bash (for orchestration scripts)

### Optional Tools
- **wrk** - HTTP benchmarking tool
  ```bash
  # Ubuntu/Debian
  sudo apt-get install wrk
  
  # macOS
  brew install wrk
  ```

- **Apache JMeter** - Full GUI/CLI load testing
  ```bash
  # Ubuntu/Debian
  sudo apt-get install jmeter
  
  # or download from apache.org
  ```

- **Grafana & Prometheus** - Metrics visualization (included in docker-compose)

## Quick Start

### Run All Tests
```bash
cd /home/engine/project
./perf/run_performance_tests.sh
```

### Run Individual Tests
```bash
# Baseline performance tests
php perf/BaselinePerformanceTest.php

# Database performance tests
php perf/DatabasePerformanceTest.php

# Encryption performance tests
php perf/EncryptionPerformanceTest.php

# Webhook stress tests
php perf/WebhookStressTest.php
```

## Test Categories

### 1. Baseline Performance Tests (`BaselinePerformanceTest.php`)

Establishes baseline metrics for core plugin operations.

**Metrics Collected:**
- Plugin activation time (target: < 1s)
- Setup wizard load time (target: < 500ms per step)
- Admin dashboard render time (target: < 500ms)
- API response times (target: < 500ms for P95)
- Bootstrap initialization time (target: < 500ms)

**Run:**
```bash
php perf/BaselinePerformanceTest.php
```

**Output:** JSON file with timestamp

### 2. Database Performance Tests (`DatabasePerformanceTest.php`)

Validates database query performance under various conditions.

**Tests Included:**
- **1000+ Records Query**: Tests query performance with large datasets
  - Target: < 500ms for full table scan
  
- **10k+ Activity Logs Pagination**: Tests pagination efficiency
  - Target: < 100ms average per page
  
- **Filter & Sort Performance**: Tests WHERE and ORDER BY clauses
  - Target: < 300ms for complex queries
  
- **Large Encrypted Payloads**: Tests handling of encrypted data
  - Sizes: 1KB, 10KB, 100KB
  - Target: < 500ms for 100KB payload
  
- **External DB Connection Overhead**: Tests connection pool performance
  - Target: Minimal overhead over WP DB
  
- **Bulk Insert Performance**: Tests batch insert efficiency
  - 1000 records bulk insert
  - Target: < 2s
  
- **Complex Joins**: Tests multi-table queries
  - Target: < 200ms

**Run:**
```bash
php perf/DatabasePerformanceTest.php
```

**Output:** Text report with detailed metrics

### 3. Encryption Performance Tests (`EncryptionPerformanceTest.php`)

Validates Crypto.php performance and key generation.

**Tests Included:**
- **1000+ Encrypt Operations**: Tests encryption throughput
  - Target: ≥ 1000 ops/sec
  
- **1000+ Decrypt Operations**: Tests decryption throughput
  - Target: ≥ 1000 ops/sec
  
- **Large Payload Encryption**: Tests handling of large data
  - Sizes: 1MB, 5MB, 10MB
  - Target: No timeout
  
- **Key Derivation**: Tests hash-based key generation
  - Target: < 5ms per derivation
  
- **IV Generation**: Tests random IV generation
  - Target: ≥ 10000 ops/sec
  - Verifies uniqueness
  
- **Concurrent Encryption**: Tests thread-safe operations
  - 100 concurrent operations × 10 ops each
  - Target: All succeed without errors

**Run:**
```bash
php perf/EncryptionPerformanceTest.php
```

**Output:** Text report with detailed metrics

### 4. Webhook Stress Tests (`WebhookStressTest.php`)

Validates webhook queueing and delivery under load.

**Tests Included:**
- **Queue 1000+ Webhooks**: Tests webhook insertion performance
  - Target: ≥ 700 webhooks/sec insertion rate
  
- **Webhook Delivery**: Tests delivery simulation with retry
  - Target: < 10ms per webhook (simulation)
  
- **Retry Mechanism**: Tests failed webhook retry logic
  - Verifies no backlog formation
  
- **Memory Usage**: Monitors memory during processing
  - Target: < 256MB peak memory
  - Checks for memory leaks

**Run:**
```bash
php perf/WebhookStressTest.php
```

**Output:** Text report with detailed metrics

### 5. API Load Tests (wrk)

Lightweight HTTP benchmarking tool for API endpoints.

**Baseline Load Test (60s):**
```bash
wrk -t 8 -c 100 -d 60s -s perf/wrk_load_test_api.lua http://localhost:8080
```

- 8 threads
- 100 concurrent connections
- Tests multiple endpoints
- Collects percentile latencies

**Concurrent Stress Test (120s):**
```bash
wrk -t 16 -c 100 -d 120s -s perf/wrk_load_test_concurrent.lua http://localhost:8080
```

- 16 threads
- 100 concurrent connections
- Higher thread count for stress testing
- Longer duration for sustained load

**Expected Output:**
- Request throughput
- Latency distribution (P95, P99)
- Error rates
- Connection statistics

### 6. API Load Tests (JMeter)

Comprehensive load testing with GUI or CLI.

**Run JMeter Test Plan:**
```bash
jmeter -n -t perf/newera_api_performance.jmx \
  -l results/jmeter_results.jtl \
  -j results/jmeter.log
```

**Test Plans Included:**
- **Baseline Thread Group**: 10 threads, 30s ramp-up, 60s duration
  - Tests normal load conditions
  
- **Stress Thread Group**: 100 threads, 60s ramp-up, 180s duration
  - Tests high concurrency
  - 5s response timeout

**Metrics Collected:**
- Response times (min, max, average, percentiles)
- Throughput (requests/second)
- Error rates
- Connection statistics

## Performance Targets (Acceptance Criteria)

### Response Time Targets
- **P50 (Median)**: < 200ms
- **P95**: < 500ms ✅ (Primary target)
- **P99**: < 2000ms ✅ (Secondary target)
- **Max**: < 5000ms

### Throughput Targets
- **Sustained**: ≥ 100 req/sec ✅
- **Peak**: ≥ 200 req/sec
- **API endpoints**: ≥ 50 req/sec each

### Resource Targets
- **Memory Usage**: < 256MB typical ✅
- **Peak Memory**: < 512MB
- **CPU Usage**: < 50% at full load ✅
- **Disk I/O**: < 100 MB/s

### Reliability Targets
- **Error Rate**: < 0.1% ✅
- **Success Rate**: > 99.9%
- **Timeout Rate**: < 0.01%
- **Webhook Delivery**: > 99.5%

## Docker Environment

### Start Services
```bash
docker-compose -f docker-compose.perf.yml up -d
```

Services:
- **WordPress** (port 8080): Main application
- **MariaDB** (port 3306): Primary database
- **External DB** (port 3307): External database test
- **Prometheus** (port 9090): Metrics collection
- **Grafana** (port 3000): Visualization

### Check Status
```bash
docker-compose -f docker-compose.perf.yml ps
docker-compose -f docker-compose.perf.yml logs -f wordpress
```

### Stop Services
```bash
docker-compose -f docker-compose.perf.yml down
```

### Clean Up (Remove Data)
```bash
docker-compose -f docker-compose.perf.yml down -v
```

## Monitoring & Metrics

### Prometheus
- Access: http://localhost:9090
- Collects metrics from WordPress and databases
- Retention: 15 days (configurable)

### Grafana
- Access: http://localhost:3000
- Default login: admin/admin
- Pre-configured Prometheus data source
- Create dashboards for custom visualization

## Results Analysis

### Output Files
- `baseline_results_YYYY-MM-DD_HH-mm-ss.json`: Baseline metrics
- `database_perf_results.txt`: Database query analysis
- `encryption_perf_results.txt`: Crypto performance data
- `webhook_stress_results.txt`: Webhook processing metrics
- `wrk_api_load.txt`: wrk HTTP benchmarking results
- `wrk_concurrent_load.txt`: wrk stress test results
- `jmeter_results.jtl`: JMeter detailed results (CSV)
- `jmeter.log`: JMeter execution log

### Interpreting Results

**Response Times:**
```
If P95 > 500ms:
1. Check database query performance
2. Review encryption operations
3. Monitor memory usage during test
4. Check for resource contention
```

**Error Rates:**
```
If errors > 0.1%:
1. Check error logs: wp-content/debug.log
2. Review timeout settings
3. Increase available memory/CPU
4. Identify specific failing endpoints
```

**Memory Usage:**
```
If peak memory > 256MB:
1. Check for memory leaks in webhooks
2. Review large query result sets
3. Optimize caching strategy
4. Consider pagination improvements
```

## Optimization Recommendations

### Database Optimization
1. Add indexes for frequently filtered columns
2. Optimize query plans with EXPLAIN
3. Use pagination for large result sets
4. Consider query result caching

### Encryption Optimization
1. Cache encryption keys when appropriate
2. Use streaming for large payloads
3. Batch encryption operations
4. Pre-generate IVs for high-throughput scenarios

### API Optimization
1. Implement response caching headers
2. Use compression for JSON responses
3. Paginate large result sets
4. Add query result caching

### Memory Optimization
1. Clear object caches after batch operations
2. Unset large variables after use
3. Use generators for large datasets
4. Monitor memory during peak load

## Continuous Integration

### GitHub Actions Integration
```yaml
name: Performance Tests
on: [push, pull_request]
jobs:
  performance:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run performance tests
        run: |
          cd /home/engine/project
          ./perf/run_performance_tests.sh
      - name: Upload results
        uses: actions/upload-artifact@v2
        with:
          name: perf-results
          path: perf/results/
```

## Troubleshooting

### Tests Won't Run
1. Check PHP is installed: `php --version`
2. Verify WordPress is accessible: `curl http://localhost:8080`
3. Check Docker is running: `docker ps`
4. Review error logs: Check output files in `perf/results/`

### Docker Fails to Start
1. Check port conflicts: `sudo netstat -tlnp`
2. Review Docker logs: `docker-compose logs`
3. Ensure adequate disk space: `df -h`
4. Rebuild images: `docker-compose build --no-cache`

### Tests Timing Out
1. Increase timeout in test scripts
2. Reduce concurrent connections/threads
3. Check system resources (CPU, RAM, disk)
4. Review database performance separately

### High Memory Usage
1. Reduce number of concurrent requests
2. Check for memory leaks in logs
3. Increase available system memory
4. Run tests at different times to reduce contention

## References

- [Apache JMeter Documentation](https://jmeter.apache.org/usermanual/index.html)
- [wrk GitHub](https://github.com/wg/wrk)
- [Prometheus Metrics](https://prometheus.io/docs/concepts/metrics/)
- [Grafana Dashboards](https://grafana.com/grafana/dashboards/)
- [WordPress Performance](https://wordpress.org/support/article/optimization/)

## Support

For issues or questions:
1. Check logs in `perf/results/`
2. Review this guide's troubleshooting section
3. Check WordPress debug.log
4. Review Docker container logs
5. File an issue with test results attached
