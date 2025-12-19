-- wrk concurrent stress test for NewEra API
-- Usage: wrk -t 16 -c 100 -d 120s -s wrk_load_test_concurrent.lua http://localhost:8080

local counter = 0
local request_id = 0

request = function()
  counter = counter + 1
  request_id = request_id + 1
  
  local endpoints = {
    '/wp-json/newera/v1/projects',
    '/wp-json/newera/v1/projects/1/activities',
    '/wp-json/newera/v1/clients/1',
    '/wp-json/newera/v1/activities?filter=recent',
  }
  
  local endpoint = endpoints[((counter - 1) % #endpoints) + 1]
  
  wrk.method = "GET"
  wrk.path = endpoint
  wrk.headers["Authorization"] = "Bearer stress-test-token"
  wrk.headers["X-Request-ID"] = tostring(request_id)
  wrk.headers["Accept"] = "application/json"
  
  return wrk.format(nil)
end

response = function(status, headers, body)
  -- Track response metrics
  if status >= 400 then
    print("[ERROR] HTTP " .. status)
  end
end

done = function(summary, latency, requests)
  print("\n========== Load Test Summary ==========")
  print("Requests: " .. summary.requests)
  print("Duration: " .. summary.duration / 1000000 .. "s")
  print("Throughput: " .. summary.requests / (summary.duration / 1000000) .. " req/s")
  print("P95 Latency: " .. latency.percentile(95) / 1000 .. "ms")
  print("P99 Latency: " .. latency.percentile(99) / 1000 .. "ms")
  print("Max Latency: " .. latency.max / 1000 .. "ms")
  print("Mean Latency: " .. latency.mean / 1000 .. "ms")
  print("Errors: " .. (summary.errors.status or 0) .. " status, " .. (summary.errors.timeout or 0) .. " timeouts")
end
