## GRMON-PHP

Run in windows powershell

- Step 1
```
docker network create debug
docker run -e PPROF_ENDPOINT=app:1234 --network=debug -v ${PWD}:/app -w /app --rm php bin/grmon
```

- Step 2
Run a container with the same network whose pprof listen on the smae PPROF_ENDPOINT
