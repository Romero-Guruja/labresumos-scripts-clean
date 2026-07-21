<?php
/**
 * Template de Email de Notificação
 *
 * @package LR_Recuperacao_Vendas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variáveis disponíveis: $order_id, $customer_name, $customer_email, $customer_phone,
// $order_total, $items, $failure_message, $failure_type, $charge_id, $dashboard_url
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333B49;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #F1CC00 0%, #E5B800 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            color: #333B49;
            font-size: 24px;
            font-weight: 700;
        }
        .header .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .intro {
            font-size: 16px;
            color: #555;
            margin-bottom: 25px;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #2A6B9F;
        }
        .info-card.warning {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .info-card h3 {
            margin: 0 0 15px 0;
            color: #333B49;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: 600;
            color: #333B49;
            min-width: 100px;
        }
        .info-value {
            color: #555;
        }
        .products-box {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }
        .products-box h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333B49;
        }
        .products-list {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #666;
            white-space: pre-line;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #2A6B9F 0%, #1E5A8A 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #888;
            font-size: 12px;
            border-top: 1px solid #e0e0e0;
        }
        .footer p {
            margin: 5px 0;
        }
        .charge-id {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">🔴</div>
            <h1>Pedido com Falha de Pagamento</h1>
        </div>
        
        <div class="content">
            <p class="intro">
                Um novo pedido falhou e requer atenção para recuperação. Veja os detalhes abaixo:
            </p>

            <div class="info-card">
                <h3>📋 Dados do Pedido</h3>
                <div class="info-row">
                    <span class="info-label">Pedido:</span>
                    <span class="info-value"><strong>#<?php echo esc_html($order_id); ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cliente:</span>
                    <span class="info-value"><?php echo esc_html($customer_name); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo esc_html($customer_email); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Telefone:</span>
                    <span class="info-value"><?php echo esc_html($customer_phone); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Valor:</span>
                    <span class="info-value"><strong><?php echo wp_kses_post($order_total); ?></strong></span>
                </div>
            </div>

            <div class="info-card warning">
                <h3>⚠️ Informações da Falha</h3>
                <div class="info-row">
                    <span class="info-label">Tipo:</span>
                    <span class="info-value"><?php echo esc_html($failure_type); ?></span>
                </div>
                <?php if (!empty($failure_message)): ?>
                <div class="info-row">
                    <span class="info-label">Mensagem:</span>
                    <span class="info-value"><?php echo esc_html($failure_message); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($charge_id)): ?>
                <div class="info-row">
                    <span class="info-label">Charge ID:</span>
                    <span class="info-value"><span class="charge-id"><?php echo esc_html($charge_id); ?></span></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="products-box">
                <h4>📦 Produtos do Pedido</h4>
                <div class="products-list"><?php echo esc_html($items); ?></div>
            </div>

            <div class="cta-section">
                <a href="<?php echo esc_url($dashboard_url); ?>" class="btn">
                    Ver Caso no Painel de Recuperação
                </a>
            </div>
        </div>

        <div class="footer">
            <p>Este email foi enviado automaticamente pelo plugin</p>
            <p><strong>Lab Resumos - Recuperação de Vendas</strong></p>
        </div>
    </div>
</body>
</html>
