
```yaml
hooks: {}
environments:
    default: sales
    sales:
        deploy_lock_timeout: 10           # default=10
        target: 'pgsql://user:pass@127.0.0.1/sales'
        history: 
            dsn: 'mssql://user:pass@127.0.0.1/db_change_management' # default=target_dsn
            schema: ''                    # default=cinch_project
            table_prefix: ''              # default=''
            auto_create_schema: true      # default=true
```
