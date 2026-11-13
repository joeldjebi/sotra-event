<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EVENT TRANS — Swagger de l’API mobile v1</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    @include('docs.partials.swagger-style')
</head>
<body>
    <header class="et-bar">
        <div class="et-brand"><span>EVENT</span> TRANS · API mobile v1</div>
        <div class="et-note">Généré le {{ now()->timezone('Africa/Abidjan')->format('d/m/Y à H:i') }} · fichier autonome, à ouvrir dans un navigateur</div>
    </header>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
    <script>
        window.ui = SwaggerUIBundle({
            spec: {!! $spec !!},
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
            displayRequestDuration: true,
            docExpansion: 'list',
            defaultModelsExpandDepth: 1,
            defaultModelExpandDepth: 3,
            filter: true,
            requestInterceptor: (request) => {
                request.headers['Accept'] = 'application/json';
                return request;
            },
        });
    </script>
</body>
</html>
