# Performance Monitoring & Metrics Guide

## Overview

This guide describes how to monitor and collect metrics during performance testing using Prometheus and Grafana.

## Prometheus Setup

### Configuration
File: `prometheus.yml`

**Global Settings:**
- Scrape interval: 15s (data collection frequency)
- Evaluation interval: 15s (rule evaluation frequency)

**Scrape Configs:**
1. **WordPress** - Application metrics
   - Endpoint: `http://wordpress:80/wp-json/newera/v1/metrics`
   - Interval: 5s (faster for API)

2. **MariaDB** - Database metrics
   - Endpoint: `db:3306`
   - Interval: 15s

### Accessing Prometheus
- **URL:** http://localhost:9090
- **Query Language:** PromQL
- **Default Retention:** 15 days

### Useful Prometheus Queries

#### Response Times
```promql
# Average response time (last 5 minutes)
rate(http_request_duration_seconds_sum[5m]) / rate(http_request_duration_seconds_count[5m])

# P95 response time
histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))

# P99 response time
histogram_quantile(0.99, rate(http_request_duration_seconds_bucket[5m]))
```

#### Throughput
```promql
# Requests per second
rate(http_requests_total[1m])

# Errors per second
rate(http_requests_total{status=~"5.."}[1m])
```

#### Resource Usage
```promql
# Memory usage
php_memory_usage_bytes

# CPU usage
rate(process_cpu_seconds_total[1m])

# Database connections
mysql_global_status_threads_connected
```

#### Database Performance
```promql
# Query rate
rate(mysql_global_status_questions[1m])

# Slow queries
mysql_global_status_slow_queries

# Table locks
mysql_global_status_table_locks_immediate
mysql_global_status_table_locks_waited
```

## Grafana Setup

### Access Grafana
- **URL:** http://localhost:3000
- **Default Credentials:** admin / admin
- **Change password on first login**

### Pre-configured Data Source
- **Prometheus:** http://prometheus:9090
- **Type:** Prometheus
- **Already configured in docker-compose.yml**

### Create Dashboard

#### 1. Response Time Dashboard
**Graph 1: Average Response Time**
```
Metric: rate(http_request_duration_seconds_sum[5m]) / rate(http_request_duration_seconds_count[5m])
Title: Average Response Time
Unit: ms
```

**Graph 2: Response Time Percentiles**
```
P50: histogram_quantile(0.50, ...)
P95: histogram_quantile(0.95, ...)
P99: histogram_quantile(0.99, ...)
```

**Graph 3: Error Rate**
```
Metric: rate(http_requests_total{status=~"5.."}[1m])
Title: Error Rate (5xx only)
Unit: req/s
```

#### 2. Resource Usage Dashboard
**Graph 1: Memory Usage**
```
Metric: php_memory_usage_bytes / 1024 / 1024
Title: PHP Memory Usage
Unit: MB
```

**Graph 2: CPU Usage**
```
Metric: rate(process_cpu_seconds_total[1m]) * 100
Title: CPU Usage
Unit: %
```

**Graph 3: Database Connections**
```
Metric: mysql_global_status_threads_connected
Title: Active Database Connections
```

#### 3. Database Performance Dashboard
**Graph 1: Query Rate**
```
Metric: rate(mysql_global_status_questions[1m])
Title: Queries Per Second
Unit: queries/s
```

**Graph 2: Slow Queries**
```
Metric: mysql_global_status_slow_queries
Title: Slow Query Count
```

**Graph 3: Lock Contention**
```
Metric: mysql_global_status_table_locks_waited
Title: Lock Wait Events
Unit: locks
```

### Dashboard Templates

Save dashboards as JSON and import them:
1. Go to Grafana > Manage dashboards
2. Click "Import dashboard"
3. Paste JSON or upload file

## Key Metrics to Monitor

### Performance Metrics

| Metric | Target | Alert Threshold | Unit |
|--------|--------|-----------------|------|
| P50 Response Time | < 200ms | > 300ms | ms |
| P95 Response Time | < 500ms | > 750ms | ms |
| P99 Response Time | < 2000ms | > 3000ms | ms |
| Error Rate | < 0.1% | > 0.5% | % |
| Throughput | > 100 req/s | < 50 req/s | req/s |

### Resource Metrics

| Metric | Target | Alert Threshold | Unit |
|--------|--------|-----------------|------|
| Memory Usage | < 256MB | > 400MB | MB |
| CPU Usage | < 50% | > 70% | % |
| Disk Usage | < 80% | > 90% | % |
| Open Connections | < 50 | > 100 | count |

### Database Metrics

| Metric | Target | Alert Threshold | Unit |
|--------|--------|-----------------|------|
| Query Rate | 1000-5000 | > 10000 | queries/s |
| Slow Queries | < 10 | > 50 | count |
| Lock Waits | 0 | > 10 | count |
| Replication Lag | < 1s | > 5s | seconds |

## Alerting Rules

### Create Alert Rules

1. Go to Prometheus > Alerts
2. Create alert rules for critical metrics

**Example Rule: High Error Rate**
```yaml
alert: HighErrorRate
expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.001
for: 5m
annotations:
  summary: "High error rate detected"
  description: "Error rate {{ $value | humanizePercentage }}"
```

**Example Rule: High Response Time**
```yaml
alert: HighResponseTime
expr: histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m])) > 0.5
for: 5m
annotations:
  summary: "High response time detected"
  description: "P95 response time: {{ $value }}s"
```

## Performance Baselines

### Baseline Collection

Run tests and record initial metrics:

```bash
# Start Prometheus/Grafana
docker-compose up -d prometheus grafana

# Run performance tests
./run_performance_tests.sh

# Export baseline data from Prometheus
curl 'http://localhost:9090/api/v1/query' \
  --data-urlencode 'query=up' > baseline_metrics.json
```

### Tracking Changes

1. **Before code changes:** Collect baseline metrics
2. **After code changes:** Re-run tests with same load
3. **Compare:** Look for changes in:
   - Response time percentiles
   - Error rate
   - Resource usage
   - Throughput

## Performance Regression Detection

### Automated Regression Detection

Create a CI/CD job to:
1. Run tests on main branch
2. Save baseline metrics
3. Run tests on PR branch
4. Compare metrics
5. Alert if P95 increases > 10%

Example script:
```bash
#!/bin/bash

# Get baseline
curl 'http://localhost:9090/api/v1/query' \
  --data-urlencode 'query=histogram_quantile(0.95, ...)' > baseline.json

# Run PR tests
./run_performance_tests.sh

# Get new metrics
curl 'http://localhost:9090/api/v1/query' \
  --data-urlencode 'query=histogram_quantile(0.95, ...)' > pr.json

# Compare
python3 compare_metrics.py baseline.json pr.json
```

## Logs & Debugging

### Application Logs
- WordPress debug log: `wp-content/debug.log`
- Plugin logs: Database `wp_newera_logs` table
- Container logs: `docker-compose logs [service]`

### Database Logs
- Slow query log: `docker-compose logs db | grep slow`
- Error log: `docker-compose logs db | grep error`

### Prometheus/Grafana Logs
- Prometheus: `docker-compose logs prometheus`
- Grafana: `docker-compose logs grafana`

## Metric Export

### Export Data for Analysis

**Prometheus API:**
```bash
# Export raw data
curl 'http://localhost:9090/api/v1/query_range' \
  --data-urlencode 'query=http_request_duration_seconds' \
  --data-urlencode 'start=2023-01-01T00:00:00Z' \
  --data-urlencode 'end=2023-01-02T00:00:00Z' \
  --data-urlencode 'step=1m' \
  > data.json
```

**Grafana API:**
```bash
# Export dashboard
curl 'http://localhost:3000/api/dashboards/db/dashboard-name' \
  -H 'Authorization: Bearer TOKEN' \
  > dashboard.json
```

### Data Analysis

Import exported JSON into:
- Python pandas: `pd.read_json('data.json')`
- Excel: Direct JSON import
- InfluxDB: Use remotewrite
- Splunk: Setup HTTP event collector

## Best Practices

### Metric Collection
1. ✅ Collect metrics BEFORE test starts
2. ✅ Collect during entire test duration
3. ✅ Allow cooldown after test ends
4. ✅ Use consistent time intervals
5. ✅ Label test runs clearly

### Analysis
1. ✅ Calculate percentiles (P50, P95, P99)
2. ✅ Identify outliers and anomalies
3. ✅ Track trends over time
4. ✅ Compare similar test scenarios
5. ✅ Document findings

### Archiving
1. ✅ Save raw metrics data
2. ✅ Export dashboard snapshots
3. ✅ Document test parameters
4. ✅ Include analysis notes
5. ✅ Version control findings

## Troubleshooting

### Prometheus Not Collecting Metrics
1. Check endpoint: `curl http://wordpress/wp-json/newera/v1/metrics`
2. Check config: `cat prometheus.yml`
3. View logs: `docker-compose logs prometheus`
4. Verify targets: http://localhost:9090/targets

### Grafana Not Displaying Data
1. Check datasource: Grafana > Configuration > Data Sources
2. Test query: Try query in Prometheus first
3. Check panel settings: Verify metric names
4. View logs: `docker-compose logs grafana`

### Missing Metrics
1. Ensure WordPress is running
2. Check PHP metrics endpoint
3. Verify Prometheus scrape config
4. Check for errors in WordPress logs

## References

- [Prometheus Documentation](https://prometheus.io/docs/)
- [Grafana Documentation](https://grafana.com/docs/)
- [PromQL Query Guide](https://prometheus.io/docs/prometheus/latest/querying/basics/)
- [WordPress Metrics](https://developer.wordpress.org/plugins/hooks/)
