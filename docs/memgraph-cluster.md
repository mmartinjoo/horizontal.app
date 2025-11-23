each node require the `max_map_count` to be set to `262144`

the init script:
```
echo "vm.max_map_count=262144" >> /etc/sysctl.d/99-memgraph.conf
sudo sysctl -p /etc/sysctl.d/99-memgraph.conf
```

that needs to run on every Memgraph node