<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Montepio Antiguedades</title>
    <style>
        :root {
            --bg: #f3efe7;
            --surface: #fffdf9;
            --text: #1d1c18;
            --muted: #6f6558;
            --brand: #123c33;
            --accent: #9d6b3f;
            --border: rgba(18, 60, 51, 0.12);
            --shadow: 0 18px 50px rgba(45, 38, 28, 0.08);
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: var(--bg); color: var(--text); }
        .shell { width: min(1240px, calc(100% - 32px)); margin: 0 auto; padding: 36px 0 72px; }
        .header, .actions, .kpis { display: flex; gap: 16px; flex-wrap: wrap; }
        .header { justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        .eyebrow { color: var(--accent); text-transform: uppercase; letter-spacing: .16em; font-size: .75rem; }
        h1 { margin: 0 0 10px; color: var(--brand); font-size: 2.3rem; }
        .lead { margin: 0; max-width: 62ch; color: var(--muted); line-height: 1.6; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 999px; border: 1px solid transparent; text-decoration: none; }
        .button-primary { background: var(--brand); color: #fff; }
        .button-secondary { background: transparent; border-color: var(--border); color: var(--brand); }
        .kpi, .table-shell { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; box-shadow: var(--shadow); }
        .kpis { margin-bottom: 24px; }
        .kpi { flex: 1 1 220px; padding: 20px; }
        .kpi span { color: var(--muted); display: block; margin-bottom: 8px; }
        .kpi strong { color: var(--brand); font-size: 1.9rem; }
        .table-shell { padding: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 10px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); text-transform: uppercase; font-size: .78rem; letter-spacing: .08em; }
    </style>
</head>
<body>
    <main class="shell">
        <section class="header">
            <div>
                <p class="eyebrow">Admin MVP</p>
                <h1>Gestion rapida de catalogo</h1>
                <p class="lead">
                    Esta pantalla es la base del administrador simple: grilla operativa,
                    acceso rapido y preparacion para edicion masiva.
                </p>
            </div>
            <div class="actions">
                <a class="button button-primary" href="#">Nuevo producto</a>
                <a class="button button-secondary" href="#">Importar CSV</a>
            </div>
        </section>

        <section class="kpis">
            <article class="kpi"><span>Productos</span><strong>10.482</strong></article>
            <article class="kpi"><span>Publicados</span><strong>9.870</strong></article>
            <article class="kpi"><span>En venta</span><strong>4.126</strong></article>
        </section>

        <section class="table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Titulo</th>
                        <th>Categoria</th>
                        <th>Venta</th>
                        <th>Precio</th>
                        <th>Publicado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['Biblioteca francesa', 'Comedor', 'Si', '$ 1.250.000', 'Si'],
                        ['Arana de cristal', 'Iluminacion', 'No', '-', 'Si'],
                        ['Par de sillas', 'Living', 'Si', '$ 780.000', 'No'],
                    ] as $row)
                        <tr>
                            @foreach ($row as $value)
                                <td>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
