<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #007bff;
            font-size: 24px;
        }
        .content {
            margin: 20px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .data-item {
            margin: 10px 0;
            padding: 8px;
            background-color: white;
            border-radius: 3px;
        }
        .data-item strong {
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $title }}</h1>
        </div>

        <div class="content">
            <p>{{ $message }}</p>

            @if($data && count($data) > 0)
                <div style="margin-top: 20px;">
                    @foreach($data as $key => $value)
                        <div class="data-item">
                            <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} ExaEarn. All rights reserved.</p>
            <p>This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
