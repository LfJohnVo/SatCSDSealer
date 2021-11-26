<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        @notifyCss

    <title>Hello, world!</title>
</head>

<body>
    <div class="container mt-4">
        <form action="{{ route('carga-fiel.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="formFile" class="form-label">.Cer</label>
                <input class="form-control" type="file" accept=".cer" name="cer" id="formFile">
            </div>
            <div class="mb-3">
                <label for="formFile" class="form-label">.Key</label>
                <input class="form-control" type="file" name="key" accept=".key" id="formFile">
            </div>
            <div class="mb-3">
                <label for="formFile" class="form-label">Contraseña</label>
                <input class="form-control" type="password" placeholder="Contraseña" name="contra"
                    aria-label="default input example">
            </div>
            <div class="mb-3">
                <label for="formFile" class="form-label">PDF a firmar</label>
                <input class="form-control" type="file" name="pdf" accept=".pdf" id="formFile">
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Enviar</button>
        </form>
    </div>

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
    </script>

        <x:notify-messages />
        @notifyJs
</body>

</html>
