# Azera MVC API

## Classes & Interfaces overview

### `Azera`

- [AppContext](AppContext.md) `Azera\AppContext`
- [ResolvedRoute](ResolvedRoute.md) `Azera\ResolvedRoute`
- [Exception](Exception.md) `Azera\Exception`

### `Azera\Boot`

- [BootstrapDiscovery](Boot_BootstrapDiscovery.md) `Azera\Boot\BootstrapDiscovery`
- [BootstrapProvider](Boot_BootstrapProvider.md) `Azera\Boot\BootstrapProvider`
- [BootstrapResolver](Boot_BootstrapResolver.md) `Azera\Boot\BootstrapResolver`
- [FileBridge](Boot_FileBridge.md) `Azera\Boot\FileBridge`

### `Azera\Cli`

- [Console](Cli_Console.md) `Azera\Cli\Console`
- [Task](Cli_Task.md) `Azera\Cli\Task`

### `Azera\Cli\Tasks`

- [AboutTask](Cli_Tasks_AboutTask.md) `Azera\Cli\Tasks\AboutTask`
- [DbTask](Cli_Tasks_DbTask.md) `Azera\Cli\Tasks\DbTask`
- [ModelTask](Cli_Tasks_ModelTask.md) `Azera\Cli\Tasks\ModelTask`
- [RoutesTask](Cli_Tasks_RoutesTask.md) `Azera\Cli\Tasks\RoutesTask`
- [ServeTask](Cli_Tasks_ServeTask.md) `Azera\Cli\Tasks\ServeTask`

### `Azera\Core`

- [Controller](Core_Controller.md) `Azera\Core\Controller`
- [Dispatcher](Core_Dispatcher.md) `Azera\Core\Dispatcher`
- [Exception](Core_Exception.md) `Azera\Core\Exception`
- [MiddlewareInterface](Core_MiddlewareInterface.md) `Azera\Core\MiddlewareInterface`
- [Model](Core_Model.md) `Azera\Core\Model`
- [ModelMapping](Core_ModelMapping.md) `Azera\Core\ModelMapping`
- [Router](Core_Router.md) `Azera\Core\Router`
- [ViewEngine](Core_ViewEngine.md) `Azera\Core\ViewEngine`

### `Azera\Core\Engines\Adapters`

- [BladeAdapter](Core_Engines_Adapters_BladeAdapter.md) `Azera\Core\Engines\Adapters\BladeAdapter`
- [PlatesAdapter](Core_Engines_Adapters_PlatesAdapter.md) `Azera\Core\Engines\Adapters\PlatesAdapter`
- [TwigAdapter](Core_Engines_Adapters_TwigAdapter.md) `Azera\Core\Engines\Adapters\TwigAdapter`

### `Azera\Core\Engines`

- [ClarityEngine](Core_Engines_ClarityEngine.md) `Azera\Core\Engines\ClarityEngine`
- [NativeEngine](Core_Engines_NativeEngine.md) `Azera\Core\Engines\NativeEngine`

### `Azera\Core\Exceptions`

- [ActionNotFoundException](Core_Exceptions_ActionNotFoundException.md) `Azera\Core\Exceptions\ActionNotFoundException`
- [ControllerNotFoundException](Core_Exceptions_ControllerNotFoundException.md) `Azera\Core\Exceptions\ControllerNotFoundException`
- [InvalidControllerException](Core_Exceptions_InvalidControllerException.md) `Azera\Core\Exceptions\InvalidControllerException`

### `Azera\Db`

- [Condition](Db_Condition.md) `Azera\Db\Condition`
- [Database](Db_Database.md) `Azera\Db\Database`
- [DatabaseManager](Db_DatabaseManager.md) `Azera\Db\DatabaseManager`
- [Exception](Db_Exception.md) `Azera\Db\Exception`
- [Paginator](Db_Paginator.md) `Azera\Db\Paginator`
- [Query](Db_Query.md) `Azera\Db\Query`
- [ResultSet](Db_ResultSet.md) `Azera\Db\ResultSet`
- [Sql](Db_Sql.md) `Azera\Db\Sql`
- [SqlCase](Db_SqlCase.md) `Azera\Db\SqlCase`

### `Azera\Db\Exceptions`

- [TransactionLostException](Db_Exceptions_TransactionLostException.md) `Azera\Db\Exceptions\TransactionLostException`

### `Azera\Http`

- [Cookie](Http_Cookie.md) `Azera\Http\Cookie`
- [Cookies](Http_Cookies.md) `Azera\Http\Cookies`
- [Request](Http_Request.md) `Azera\Http\Request`
- [Response](Http_Response.md) `Azera\Http\Response`
- [Session](Http_Session.md) `Azera\Http\Session`
- [SessionMiddleware](Http_SessionMiddleware.md) `Azera\Http\SessionMiddleware`
- [UploadedFile](Http_UploadedFile.md) `Azera\Http\UploadedFile`

### `Azera\Sync`

- [CodeGenerator](Sync_CodeGenerator.md) `Azera\Sync\CodeGenerator`
- [ModelDiff](Sync_ModelDiff.md) `Azera\Sync\ModelDiff`
- [DiffOperation](Sync_DiffOperation.md) `Azera\Sync\DiffOperation`
- [AddProperty](Sync_AddProperty.md) `Azera\Sync\AddProperty`
- [RemoveProperty](Sync_RemoveProperty.md) `Azera\Sync\RemoveProperty`
- [AddAccessor](Sync_AddAccessor.md) `Azera\Sync\AddAccessor`
- [UpdatePropertyType](Sync_UpdatePropertyType.md) `Azera\Sync\UpdatePropertyType`
- [UpdatePropertyComment](Sync_UpdatePropertyComment.md) `Azera\Sync\UpdatePropertyComment`
- [UpdateClassComment](Sync_UpdateClassComment.md) `Azera\Sync\UpdateClassComment`
- [ModelParser](Sync_ModelParser.md) `Azera\Sync\ModelParser`
- [ParsedModel](Sync_ParsedModel.md) `Azera\Sync\ParsedModel`
- [ParsedProperty](Sync_ParsedProperty.md) `Azera\Sync\ParsedProperty`
- [SyncOptions](Sync_SyncOptions.md) `Azera\Sync\SyncOptions`
- [SyncResult](Sync_SyncResult.md) `Azera\Sync\SyncResult`
- [SyncRunner](Sync_SyncRunner.md) `Azera\Sync\SyncRunner`

### `Azera\Sync\Schema`

- [MySqlSchemaProvider](Sync_Schema_MySqlSchemaProvider.md) `Azera\Sync\Schema\MySqlSchemaProvider`
- [PostgresSchemaProvider](Sync_Schema_PostgresSchemaProvider.md) `Azera\Sync\Schema\PostgresSchemaProvider`
- [SchemaProvider](Sync_Schema_SchemaProvider.md) `Azera\Sync\Schema\SchemaProvider`
- [TableSchema](Sync_Schema_TableSchema.md) `Azera\Sync\Schema\TableSchema`
- [ColumnSchema](Sync_Schema_ColumnSchema.md) `Azera\Sync\Schema\ColumnSchema`
- [IndexSchema](Sync_Schema_IndexSchema.md) `Azera\Sync\Schema\IndexSchema`
- [SchemaProviderFactory](Sync_Schema_SchemaProviderFactory.md) `Azera\Sync\Schema\SchemaProviderFactory`
- [SqliteSchemaProvider](Sync_Schema_SqliteSchemaProvider.md) `Azera\Sync\Schema\SqliteSchemaProvider`

### `Azera\Validation`

- [FieldValidator](Validation_FieldValidator.md) `Azera\Validation\FieldValidator`
- [ValidationException](Validation_ValidationException.md) `Azera\Validation\ValidationException`
- [Validator](Validation_Validator.md) `Azera\Validation\Validator`

