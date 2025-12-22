<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Website Maintenance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-yellow-400 min-h-screen flex items-center justify-center">

<div class="bg-white rounded-xl shadow-xl max-w-4xl w-full p-8 flex flex-col md:flex-row items-center">
    
    <div class="md:w-1/2 mb-6 md:mb-0">
        <h1 class="text-4xl font-bold mb-4">Website Maintenance</h1>
        <p class="text-gray-600 text-lg mb-3">
            Kami sedang melakukan pemeliharaan sistem untuk meningkatkan layanan.
        </p>
        <p class="text-gray-500">
            Silakan kembali beberapa saat lagi 🙏
        </p>
    </div>

    <div class="md:w-1/2">
        <img src="{{ asset('images/maintenance.jpg') }}" alt="Maintenance">
    </div>

</div>

</body>
</html>
