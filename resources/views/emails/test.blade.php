<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Conexión</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="100%" style="max-width:600px;" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">
                                ✅ ¡Conexión Exitosa!
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="font-size: 16px; color: #333333; line-height: 1.6; margin: 0 0 20px 0;">
                                La configuración de correo electrónico está funcionando correctamente.
                            </p>
                            
                            <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                                <h3 style="color: #374151; margin: 0 0 15px 0; font-size: 16px;">📋 Detalles de la prueba:</h3>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                            <span style="color: #6b7280; font-size: 14px;">📅 Fecha:</span>
                                        </td>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">
                                            <span style="color: #111827; font-size: 14px; font-weight: 500;">{{ $sentAt }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                            <span style="color: #6b7280; font-size: 14px;">📧 Estado:</span>
                                        </td>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">
                                            <span style="color: #10b981; font-size: 14px; font-weight: 500;">✓ Entregado</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <span style="color: #6b7280; font-size: 14px;">🔧 Servicio:</span>
                                        </td>
                                        <td style="padding: 8px 0; text-align: right;">
                                            <span style="color: #111827; font-size: 14px; font-weight: 500;">Email Marketing</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <p style="font-size: 14px; color: #6b7280; line-height: 1.6; margin: 0;">
                                Este es un correo de prueba automático enviado desde el sistema de configuración de Email Marketing. 
                                Si recibiste este mensaje, significa que tu servidor de correo está correctamente configurado y listo para enviar comunicaciones.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="font-size: 12px; color: #9ca3af; margin: 0;">
                                Enviado automáticamente por el sistema de Email Marketing<br>
                                {{ now()->year }} - Todos los derechos reservados
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>