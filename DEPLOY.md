# Deploy

Los datos generados por el sitio no deben viajar con cada deploy.

## Archivos persistentes en produccion

- `data/montepio.sqlite`
- `data/montepio.sqlite-wal`
- `data/montepio.sqlite-shm`
- `data/pass.php`
- `uploads/categories/`
- `uploads/products/`
- `uploads/site/`
- `uploads/temp/`

## Antes de deployar

1. Hacer backup de `data/montepio.sqlite` en el servidor.
2. Subir solo codigo y assets versionados.
3. Excluir `data/*.sqlite*` y las carpetas generadas dentro de `uploads/`.
4. Verificar permisos de escritura para `data/` y `uploads/`.

Si se usa una herramienta tipo FTP, rsync o panel de hosting, esas rutas tienen que quedar como carpetas persistentes del servidor. El deploy no debe borrarlas ni reemplazarlas.
