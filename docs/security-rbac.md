# RBAC e IDOR

## Resolución de permisos

`UserAccessResolver` carga roles, permisos directos y permisos heredados una sola vez por usuario y solicitud. El servicio está registrado con alcance `scoped`, por lo que no comparte decisiones entre solicitudes HTTP ni entre trabajos de cola.

Este diseño evita dos riesgos:

- no consulta `Schema::hasTable()` por cada llamada a `can()`;
- una revocación no queda retenida en un caché persistente con TTL.

Las mutaciones de acceso en código de aplicación deben usar `User::syncRoles()` y `User::syncPermissions()`. Ambos métodos sincronizan el pivote, invalidan la entrada de la solicitud actual y descargan las relaciones Eloquent. Migraciones y seeders pueden escribir los pivotes directamente porque no atienden solicitudes concurrentes.

## Autorización por registro

Un permiso de módulo no sustituye una policy de registro. Los controladores de archivos privados deben autorizar el modelo enlazado antes de abrir el disco `private`.

Las pruebas de regresión cubren como mínimo:

- 200 para el propietario;
- 403 al cambiar el ID por el de otro cliente;
- 404 para un ID inexistente;
- contenido servido desde el disco privado.

El rol `cliente_tienda` nunca obtiene acceso global a máquinas por tener permisos para crear solicitudes comerciales. `MachinePolicy` resuelve primero el alcance del cliente y valida `entities.user_id` para cada registro.
