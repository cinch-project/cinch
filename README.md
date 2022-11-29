
# TODO
1. https://www.datasunrise.com/professional-info/ddl-in-transactions/#:~:text=Oracle%20does%20not%20support%20transactional,command%20as%20a%20separate%20transaction.
2. transaction DDL - oracle and mysql do not support this. DDL in these databases are auto-commit, meaning any previously
open transaction is committed and the DDL statement is executed as a single transaction. Highly recommended to 
isolate DDL statements into their own change scripts: one per script. cinch commits per change script: 
begin, execute change script, update history, commit. If a script fails, cinch will know what has not been committed
to the database yet. If a change script contains DML-1, DML-2, DDL-1, DML-3 and DML-3 fails, nothing can be rolled 
back and history was not updated. This is because DDL-1 auto-committed DML-1 and DML-2. This is not the case if a
change script is all DML.
3. maybe add ability to mark transactions? --@commit \[optional name], @script commit, @script revert
   * only need @commit. everything up to that point is committed and a new transaction is started
   * wrap session: ScriptSession::commit()
4. implicit rollback on error is unreliable, even in databases with mechanisms to support this. ALWAYS issue ROLLBACK.

* MigrationLog migration-log.yaml

/
  store.yml
  directory/
      migration.sql
  directory/

lender template, javier replenishment job (run it)

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