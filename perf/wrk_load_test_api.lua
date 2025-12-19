-- wrk load test for NewEra API endpoints
-- Usage: wrk -t 8 -c 100 -d 60s -s wrk_load_test_api.lua http://localhost:8080

local counter = 0
local endpoints = {
  '/wp-json/newera/v1/projects',
  '/wp-json/newera/v1/activities',
  '/wp-json/newera/v1/clients',
  '/wp-json/newera/v1/team-members',
  '/wp-json/newera/v1/auth/status',
}

request = function()
  counter = counter + 1
  local endpoint = endpoints[(counter % #endpoints) + 1]
  
  wrk.method = "GET"
  wrk.path = endpoint .. "?page=1&per_page=20"
  wrk.headers["Authorization"] = "Bearer test-token"
  wrk.headers["Accept"] = "application/json"
  
  return wrk.format(nil)
end

response = function(status, headers, body)
  if status < 200 or status >= 300 then
    print("HTTP " .. status .. " for path: " .. wrk.path)
  end
end
