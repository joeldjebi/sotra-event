<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>EVENT TRANS — Swagger de l’API mobile</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    @include('docs.partials.swagger-style')
</head>
<body>
    <header class="et-bar">
        <div class="et-brand"><span>EVENT</span> TRANS · API mobile v1</div>
        <nav class="et-links">
            <a href="{{ url('docs/api') }}">Vue détaillée</a>
            <a href="{{ $specUrl }}" download="eventtrans-openapi.json">Télécharger le fichier OpenAPI</a>
        </nav>
    </header>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: @json($specUrl),
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
            displayRequestDuration: true,
            docExpansion: 'list',
            defaultModelsExpandDepth: 1,
            defaultModelExpandDepth: 3,
            filter: true,
            tryItOutEnabled: false,
            requestInterceptor: (request) => {
                request.headers['Accept'] = 'application/json';
                return request;
            },
        });
    </script>
</body>
</html>
