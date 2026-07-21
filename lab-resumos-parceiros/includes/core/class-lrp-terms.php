<?php
/**
 * Gerenciador de Termos de Afiliação
 *
 * @package Lab_Resumos_Parceiros
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LRP_Terms
 * 
 * Gerencia os termos de afiliação, versões e aceites.
 */
class LRP_Terms {

    /**
     * Instância única
     *
     * @var LRP_Terms|null
     */
    private static $instance = null;

    /**
     * Versão atual dos termos
     */
    const CURRENT_VERSION = '1.0';

    /**
     * Retorna instância única
     *
     * @return LRP_Terms
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        // Hook para verificar termos pendentes no dashboard
        add_action('lrp_before_dashboard_render', [$this, 'check_pending_terms']);
        
        // Hook para notificar afiliados sobre novos termos (2 parâmetros: version, changelog)
        add_action('lrp_terms_version_updated', [$this, 'notify_affiliates_new_terms'], 10, 2);
    }

    /**
     * Retorna a versão atual dos termos
     *
     * @return string
     */
    public function get_current_version() {
        return get_option('lrp_terms_current_version', self::CURRENT_VERSION);
    }

    /**
     * Retorna o conteúdo dos termos
     *
     * @param string|null $version Versão específica ou null para atual
     * @return array|null
     */
    public function get_terms($version = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_versions';
        
        // Verifica se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if (!$table_exists) {
            return $this->get_default_terms();
        }
        
        if ($version) {
            $terms = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE version = %s",
                $version
            ), ARRAY_A);
        } else {
            $terms = $wpdb->get_row(
                "SELECT * FROM $table WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1",
                ARRAY_A
            );
        }
        
        if (!$terms) {
            // Retorna termos padrão se não houver no banco
            return $this->get_default_terms();
        }
        
        $terms['sections'] = json_decode($terms['sections'], true);
        return $terms;
    }

    /**
     * Retorna os termos padrão
     *
     * @return array
     */
    private function get_default_terms() {
        return [
            'id' => 0,
            'version' => self::CURRENT_VERSION,
            'title' => 'Termos e Condições do Programa de Parceiros Lab Resumos',
            'intro' => '<p><strong>SOLUCOES EDUCACIONAIS INTELIGENTES LTDA</strong>, pessoa jurídica de direito privado, doravante denominada "LAB RESUMOS" ou "EMPRESA", estabelece os presentes Termos e Condições que regulam a participação no Programa de Parceiros Lab Resumos, doravante denominado "PROGRAMA".</p>
<p>Ao se cadastrar no Programa, o participante, doravante denominado "PARCEIRO", declara ter lido, compreendido e aceito integralmente todos os termos e condições aqui estabelecidos, concordando em cumpri-los de forma irrestrita.</p>',
            'sections' => $this->get_default_sections(),
            'is_active' => 1,
            'created_at' => current_time('mysql'),
        ];
    }

    /**
     * Retorna seções padrão dos termos
     *
     * @return array
     */
    private function get_default_sections() {
        return [
            [
                'id' => 'definicoes',
                'title' => '1. Definições',
                'content' => '<p>Para fins deste instrumento, considera-se:</p>
<ul>
<li><strong>Parceiro:</strong> pessoa física ou jurídica aprovada no Programa que divulga os produtos da Lab Resumos em troca de comissões sobre as vendas realizadas.</li>
<li><strong>Cupom de Desconto:</strong> código alfanumérico exclusivo atribuído ao Parceiro, que concede desconto ao cliente e gera comissão ao Parceiro.</li>
<li><strong>Link de Referência:</strong> URL personalizada com código de rastreamento que identifica o Parceiro como origem da indicação.</li>
<li><strong>Cookie de Rastreamento:</strong> arquivo temporário armazenado no navegador do cliente que identifica a origem da indicação por período determinado.</li>
<li><strong>Comissão:</strong> valor devido ao Parceiro sobre vendas efetivamente realizadas e confirmadas por meio de seu cupom ou link.</li>
<li><strong>Rede de Parceiros:</strong> estrutura multinível composta por parceiros indicados direta ou indiretamente pelo Parceiro.</li>
<li><strong>Fechamento:</strong> apuração periódica das comissões aprovadas para pagamento.</li>
<li><strong>Venda Válida:</strong> transação concluída, paga e não cancelada ou reembolsada, realizada por cliente elegível.</li>
<li><strong>Painel do Parceiro:</strong> área restrita do sistema onde o Parceiro pode acompanhar suas vendas, comissões, configurações e demais informações do Programa.</li>
</ul>',
            ],
            [
                'id' => 'objeto',
                'title' => '2. Objeto do Programa',
                'content' => '<p><strong>2.1.</strong> O Programa tem por objeto a promoção e divulgação dos cursos e materiais educacionais da Lab Resumos pelo Parceiro, mediante o pagamento de comissões sobre vendas válidas atribuídas ao Parceiro.</p>
<p><strong>2.2.</strong> A participação no Programa não estabelece vínculo empregatício, societário, de representação comercial, mandato, franquia ou qualquer outro tipo de relação entre o Parceiro e a Lab Resumos.</p>
<p><strong>2.3.</strong> O Parceiro atua exclusivamente em seu próprio nome e por sua conta e risco, não podendo se apresentar como funcionário, representante, preposto ou agente da Lab Resumos.</p>',
            ],
            [
                'id' => 'cadastro',
                'title' => '3. Cadastro e Aprovação',
                'content' => '<p><strong>3.1.</strong> Para participar do Programa, o interessado deve realizar cadastro completo na plataforma, fornecendo informações verdadeiras, atuais e completas.</p>
<p><strong>3.2.</strong> Para <strong>Pessoa Jurídica</strong>, são obrigatórios: CNPJ válido e ativo, razão social, dados bancários de titularidade da empresa e capacidade de emissão de Nota Fiscal de Serviços.</p>
<p><strong>3.3.</strong> Para <strong>Pessoa Física</strong>, são obrigatórios: CPF válido, endereço completo, telefone e dados bancários de sua titularidade.</p>
<p><strong>3.4.</strong> A Lab Resumos se reserva o direito de aprovar ou rejeitar qualquer solicitação de cadastro a seu exclusivo critério, sem necessidade de justificativa.</p>
<p><strong>3.5.</strong> É permitido apenas um cadastro por CPF ou CNPJ. Cadastros múltiplos utilizando o mesmo documento, dados bancários idênticos ou informações que indiquem ser a mesma pessoa serão considerados irregulares e poderão ser cancelados.</p>
<p><strong>3.6.</strong> É vedado o cadastro de cônjuges, companheiros ou parentes até segundo grau do mesmo Parceiro, quando residentes no mesmo domicílio, salvo autorização prévia e expressa da Lab Resumos. Essa restrição visa prevenir simulação de vendas entre membros do mesmo núcleo familiar.</p>
<p><strong>3.7.</strong> O Parceiro é responsável pela veracidade e atualização de seus dados cadastrais, devendo comunicar à Lab Resumos qualquer alteração em até 5 dias úteis.</p>',
            ],
            [
                'id' => 'divulgacao',
                'title' => '4. Formas de Divulgação e Comissionamento',
                'content' => '<h4>4.1. CUPOM DE DESCONTO</h4>
<p><strong>4.1.1.</strong> O Parceiro receberá um cupom de desconto exclusivo que poderá ser utilizado pelos clientes no momento da compra.</p>
<p><strong>4.1.2.</strong> O cupom concede ao cliente um percentual de desconto sobre o valor do produto, conforme definido pela Lab Resumos e informado no Painel do Parceiro.</p>
<p><strong>4.1.3.</strong> A comissão do Parceiro por vendas realizadas com cupom corresponde a um percentual sobre o valor efetivamente pago pelo cliente, conforme definido pela Lab Resumos e informado no Painel do Parceiro.</p>
<p><strong>4.1.4.</strong> O código do cupom é pessoal e intransferível.</p>

<h4>4.2. LINK DE REFERÊNCIA</h4>
<p><strong>4.2.1.</strong> O Parceiro receberá um link de referência personalizado para divulgação.</p>
<p><strong>4.2.2.</strong> O link não concede desconto automático ao cliente.</p>
<p><strong>4.2.3.</strong> A comissão do Parceiro por vendas realizadas via link corresponde a um percentual sobre o valor efetivamente pago pelo cliente, conforme definido pela Lab Resumos e informado no Painel do Parceiro.</p>
<p><strong>4.2.4.</strong> O cookie de rastreamento tem validade por período determinado, conforme definido pela Lab Resumos e informado no Painel do Parceiro.</p>

<h4>4.3. USO COMBINADO</h4>
<p><strong>4.3.1.</strong> Quando o cliente utilizar link e cupom do mesmo Parceiro na mesma transação, as comissões poderão ser somadas, conforme regras vigentes no Programa.</p>
<p><strong>4.3.2.</strong> Quando o cliente utilizar link de um Parceiro e cupom de outro Parceiro diferente, cada um receberá sua respectiva comissão de forma independente.</p>

<h4>4.4. CONFLITO COM OUTROS DESCONTOS</h4>
<p><strong>4.4.1.</strong> Quando houver conflito entre o cupom do Parceiro e outros descontos institucionais oferecidos pela Lab Resumos, prevalecerá automaticamente o maior desconto para o cliente, salvo configuração diversa.</p>
<p><strong>4.4.2.</strong> Se o desconto institucional for aplicado em substituição ao cupom do Parceiro, a comissão poderá não ser devida, conforme configuração específica do Programa.</p>

<h4>4.5. PERCENTUAIS E CONDIÇÕES VARIÁVEIS</h4>
<p><strong>4.5.1.</strong> Os percentuais de comissão, percentuais de desconto ao cliente, validade do cookie e demais condições comerciais podem variar conforme o Parceiro, o produto, campanhas promocionais ou outros critérios definidos pela Lab Resumos.</p>
<p><strong>4.5.2.</strong> As condições específicas aplicáveis a cada Parceiro estarão sempre disponíveis e atualizadas no Painel do Parceiro.</p>
<p><strong>4.5.3.</strong> A Lab Resumos pode, a qualquer momento, criar condições especiais, bônus, campanhas ou incentivos temporários, que serão comunicados aos Parceiros elegíveis.</p>',
            ],
            [
                'id' => 'rede',
                'title' => '5. Rede de Parceiros (Sistema Multinível)',
                'content' => '<p><strong>5.1.</strong> O Parceiro pode indicar novos parceiros para o Programa utilizando seu link de convite exclusivo.</p>
<p><strong>5.2.</strong> A estrutura da rede possui múltiplos níveis, conforme definido pela Lab Resumos:</p>
<ul>
<li><strong>a) Nível 1:</strong> vendas diretas do próprio Parceiro (comissão conforme item 4);</li>
<li><strong>b) Níveis subsequentes:</strong> vendas realizadas por parceiros indicados direta ou indiretamente, com percentuais de comissão específicos para cada nível.</li>
</ul>
<p><strong>5.3.</strong> Os percentuais de comissão para cada nível da rede, bem como a quantidade de níveis contemplados, estão disponíveis no Painel do Parceiro e podem ser alterados pela Lab Resumos.</p>

<h4>5.4. REQUISITO DE ATIVIDADE PARA COMISSÕES DE REDE</h4>
<p><strong>5.4.1.</strong> Para receber comissões dos níveis subsequentes da rede, o Parceiro deve estar classificado como "Ativo".</p>
<p><strong>5.4.2.</strong> Os critérios de atividade (quantidade mínima de vendas e período de apuração) estão definidos no Painel do Parceiro e podem ser alterados pela Lab Resumos.</p>
<p><strong>5.4.3.</strong> Parceiros novos possuem período de proteção durante o qual são considerados ativos automaticamente, conforme prazo definido pela Lab Resumos.</p>
<p><strong>5.4.4.</strong> Se o Parceiro estiver inativo, as comissões de rede que lhe seriam devidas serão redirecionadas para o próximo parceiro ativo na cadeia hierárquica superior.</p>
<p><strong>5.4.5.</strong> A inatividade não afeta as comissões diretas do Parceiro (Nível 1), que continuam sendo devidas normalmente.</p>
<p><strong>5.4.6.</strong> A estrutura da rede permanece intacta independentemente do status de atividade. Ao retornar à condição de ativo, o Parceiro volta a receber as comissões de rede.</p>',
            ],
            [
                'id' => 'calculo',
                'title' => '6. Cálculo e Apuração das Comissões',
                'content' => '<p><strong>6.1.</strong> As comissões são calculadas sobre o valor efetivamente pago pelo cliente, já deduzidos quaisquer descontos aplicados.</p>
<p><strong>6.2.</strong> Somente geram comissão as vendas de produtos elegíveis ao Programa. A Lab Resumos pode, a seu exclusivo critério, excluir produtos ou categorias específicas do comissionamento, bem como definir regras específicas por Parceiro.</p>
<p><strong>6.3.</strong> As comissões passam pelos seguintes status:</p>
<ul>
<li><strong>a) Pendente:</strong> venda realizada, aguardando confirmação do pagamento;</li>
<li><strong>b) Aprovada:</strong> pagamento confirmado, venda válida;</li>
<li><strong>c) Paga:</strong> comissão transferida ao Parceiro;</li>
<li><strong>d) Cancelada:</strong> venda cancelada, reembolsada ou não concretizada.</li>
</ul>

<h4>6.4. PERÍODO DE CARÊNCIA</h4>
<p><strong>6.4.1.</strong> Todas as vendas estão sujeitas a período de carência contado da confirmação do pagamento, conforme prazo definido pela Lab Resumos.</p>
<p><strong>6.4.2.</strong> Durante o período de carência, a comissão permanece em status pendente.</p>
<p><strong>6.4.3.</strong> Se houver cancelamento ou reembolso durante o período de carência, a comissão será automaticamente cancelada.</p>

<h4>6.5. AJUSTES</h4>
<p><strong>6.5.1.</strong> A Lab Resumos pode realizar ajustes manuais nas comissões, positivos ou negativos, mediante justificativa registrada no sistema.</p>
<p><strong>6.5.2.</strong> Ajustes negativos podem ser aplicados para correção de comissões indevidas, fraudes identificadas ou estornos.</p>',
            ],
            [
                'id' => 'pagamento',
                'title' => '7. Fechamento e Pagamento',
                'content' => '<p><strong>7.1.</strong> O fechamento das comissões ocorre periodicamente, conforme calendário definido pela Lab Resumos e informado no Painel do Parceiro.</p>
<p><strong>7.2.</strong> Existe um valor mínimo para saque, definido pela Lab Resumos e informado no Painel do Parceiro. Se o saldo não atingir o mínimo, será acumulado para o próximo fechamento.</p>
<p><strong>7.3.</strong> A periodicidade de pagamento pode variar conforme definido pela Lab Resumos, podendo ser mensal, bimestral, trimestral ou outra periodicidade, conforme informado no Painel do Parceiro.</p>

<h4>7.4. PROCEDIMENTO PARA PAGAMENTO</h4>
<p><strong>7.4.1.</strong> Para <strong>Pessoa Jurídica:</strong> o Parceiro deve emitir Nota Fiscal de Serviços no valor do fechamento, com a descrição "Prestação de serviços de divulgação e indicação comercial", contra os dados da empresa informados no Painel do Parceiro. Após aprovação da NF, o pagamento será realizado via PIX no prazo informado no Painel.</p>
<p><strong>7.4.2.</strong> Para <strong>Pessoa Física:</strong> a Lab Resumos emitirá Recibo de Pagamento Autônomo (RPA) com as devidas retenções legais (INSS, IRRF conforme tabela progressiva). O pagamento será realizado via PIX no prazo informado no Painel.</p>
<p><strong>7.5.</strong> O Parceiro é exclusivamente responsável pelo cumprimento de suas obrigações tributárias, incluindo declaração de rendimentos às autoridades fiscais.</p>',
            ],
            [
                'id' => 'condutas-vedadas',
                'title' => '8. Condutas Vedadas',
                'content' => '<p>O Parceiro se compromete a <strong>NÃO</strong> praticar, direta ou indiretamente, qualquer das seguintes condutas, sob pena das sanções previstas neste instrumento:</p>

<h4>8.1. FRAUDE E SIMULAÇÃO</h4>
<p><strong>8.1.1.</strong> Realizar compras próprias utilizando seu cupom ou link de referência, salvo autorização expressa da Lab Resumos.</p>
<p><strong>8.1.2.</strong> Solicitar, induzir, combinar ou facilitar que familiares, amigos, conhecidos ou terceiros realizem compras exclusivamente para gerar comissões, sem real interesse no produto.</p>
<p><strong>8.1.3.</strong> Criar, utilizar ou disponibilizar mecanismos automatizados (bots, scripts, softwares) para simular cliques, acessos, cadastros ou compras.</p>
<p><strong>8.1.4.</strong> Fraudar ou tentar fraudar o sistema de rastreamento de vendas por qualquer meio, incluindo manipulação de cookies, URLs, códigos-fonte ou quaisquer outros elementos técnicos.</p>
<p><strong>8.1.5.</strong> Realizar vendas combinadas, trocas de favores, compras recíprocas ou qualquer esquema que caracterize simulação de vendas.</p>
<p><strong>8.1.6.</strong> Fornecer dados falsos, incompletos ou de terceiros no cadastro.</p>
<p><strong>8.1.7.</strong> Criar múltiplos cadastros para simular rede de parceiros ou inflar artificialmente os números de vendas.</p>

<h4>8.2. USO INDEVIDO DA MARCA</h4>
<p><strong>8.2.1.</strong> Utilizar a marca "Lab Resumos", logotipo, nome comercial ou qualquer elemento de identidade visual da empresa de forma não autorizada ou que possa confundir os consumidores.</p>
<p><strong>8.2.2.</strong> Criar sites, páginas, perfis em redes sociais ou qualquer presença digital que simule ser oficial da Lab Resumos ou que possa induzir o público a acreditar tratar-se de canal oficial.</p>
<p><strong>8.2.3.</strong> Registrar domínios que contenham o nome "Lab Resumos", "LabResumos" ou variações similares (typosquatting).</p>
<p><strong>8.2.4.</strong> Utilizar a marca da Lab Resumos em anúncios pagos (Google Ads, Facebook Ads, Instagram Ads ou qualquer plataforma de mídia paga) como palavra-chave, nome de campanha ou no texto do anúncio, sem autorização prévia e expressa.</p>

<h4>8.3. SPAM E COMUNICAÇÃO NÃO AUTORIZADA</h4>
<p><strong>8.3.1.</strong> Enviar mensagens eletrônicas em massa (e-mail, SMS, WhatsApp, Telegram ou qualquer outro meio) não solicitadas promovendo os produtos da Lab Resumos.</p>
<p><strong>8.3.2.</strong> Realizar postagens excessivas, repetitivas ou consideradas spam em fóruns, grupos, comunidades online ou redes sociais.</p>
<p><strong>8.3.3.</strong> Utilizar listas de e-mail ou contatos adquiridas de terceiros sem consentimento dos destinatários.</p>

<h4>8.4. INFORMAÇÕES FALSAS OU ENGANOSAS</h4>
<p><strong>8.4.1.</strong> Divulgar informações falsas, incompletas, desatualizadas ou enganosas sobre os produtos, preços, conteúdo ou condições oferecidas pela Lab Resumos.</p>
<p><strong>8.4.2.</strong> Criar promoções, descontos ou condições inexistentes em nome da Lab Resumos.</p>
<p><strong>8.4.3.</strong> Prometer resultados, aprovações ou benefícios que a Lab Resumos não garante.</p>
<p><strong>8.4.4.</strong> Utilizar depoimentos falsos ou adulterados.</p>

<h4>8.5. REPRESENTAÇÃO INDEVIDA</h4>
<p><strong>8.5.1.</strong> Apresentar-se como funcionário, representante, vendedor, revendedor, agente ou qualquer outro vínculo com a Lab Resumos que não seja exclusivamente de Parceiro do Programa.</p>
<p><strong>8.5.2.</strong> Realizar atendimento a clientes em nome da Lab Resumos ou assumir funções de suporte ao cliente.</p>
<p><strong>8.5.3.</strong> Firmar compromissos, fazer promessas ou assumir obrigações em nome da Lab Resumos.</p>

<h4>8.6. CONDUTAS ILÍCITAS OU ANTIÉTICAS</h4>
<p><strong>8.6.1.</strong> Divulgar os produtos da Lab Resumos em sites ou plataformas que contenham conteúdo ilegal, imoral, difamatório, discriminatório, pornográfico, violento ou que promova atividades ilícitas.</p>
<p><strong>8.6.2.</strong> Violar direitos de propriedade intelectual de terceiros.</p>
<p><strong>8.6.3.</strong> Utilizar técnicas de manipulação ou engenharia social para induzir compras.</p>
<p><strong>8.6.4.</strong> Interceptar, redirecionar ou desviar tráfego destinado ao site oficial da Lab Resumos.</p>

<h4>8.7. USO INDEVIDO DE MATERIAIS DE DIVULGAÇÃO</h4>
<p><strong>8.7.1.</strong> Enviar, compartilhar ou disponibilizar a potenciais clientes quaisquer materiais de divulgação, amostras, trechos de conteúdo, PDFs, imagens, vídeos ou qualquer outro material que não tenha sido expressamente fornecido e autorizado pela Lab Resumos para aquela finalidade específica.</p>
<p><strong>8.7.2.</strong> Utilizar materiais de divulgação desatualizados, descontinuados ou que a Lab Resumos tenha deixado de disponibilizar, ainda que tenham sido fornecidos anteriormente.</p>
<p><strong>8.7.3.</strong> Criar, adaptar, modificar ou produzir materiais próprios de divulgação que contenham conteúdo, trechos, imagens ou informações extraídas dos produtos da Lab Resumos sem autorização expressa.</p>
<p><strong>8.7.4.</strong> Compartilhar, ainda que parcialmente, o conteúdo dos cursos, resumos, materiais didáticos ou qualquer propriedade intelectual da Lab Resumos como estratégia de captação de clientes.</p>
<p><strong>8.7.5.</strong> O Parceiro deve utilizar exclusivamente os materiais de divulgação vigentes, disponibilizados no Painel do Parceiro, na forma e para as finalidades ali indicadas. A Lab Resumos pode atualizar, substituir ou descontinuar materiais a qualquer momento, cabendo ao Parceiro utilizar sempre as versões mais recentes disponíveis.</p>',
            ],
            [
                'id' => 'propriedade-intelectual',
                'title' => '9. Propriedade Intelectual',
                'content' => '<p><strong>9.1.</strong> A Lab Resumos autoriza o Parceiro a utilizar sua marca, logotipo e materiais de marketing disponibilizados no Painel do Parceiro exclusivamente para fins de divulgação no âmbito do Programa.</p>
<p><strong>9.2.</strong> A autorização de uso é limitada, não exclusiva, intransferível e revogável a qualquer tempo.</p>
<p><strong>9.3.</strong> O Parceiro não pode modificar, alterar, distorcer ou utilizar os elementos de marca de forma que prejudique a imagem ou reputação da Lab Resumos.</p>
<p><strong>9.4.</strong> Todo o conteúdo, materiais, cursos e propriedade intelectual da Lab Resumos permanecem de titularidade exclusiva da empresa.</p>',
            ],
            [
                'id' => 'monitoramento',
                'title' => '10. Monitoramento e Auditoria',
                'content' => '<p><strong>10.1.</strong> A Lab Resumos se reserva o direito de monitorar, auditar e investigar as atividades do Parceiro para verificar o cumprimento destes Termos.</p>
<p><strong>10.2.</strong> O Parceiro concorda em fornecer informações e documentos solicitados pela Lab Resumos para fins de verificação de conformidade.</p>
<p><strong>10.3.</strong> A Lab Resumos utiliza tecnologias e ferramentas de análise para identificar padrões suspeitos, tráfego fraudulento e outras irregularidades.</p>
<p><strong>10.4.</strong> A análise das métricas, incluindo taxas de conversão, origem do tráfego, padrões de comportamento e outras informações, pode ser utilizada como evidência de fraude ou violação destes Termos.</p>',
            ],
            [
                'id' => 'penalidades',
                'title' => '11. Penalidades e Sanções',
                'content' => '<p><strong>11.1.</strong> Em caso de violação de qualquer disposição destes Termos, a Lab Resumos poderá, a seu exclusivo critério e sem necessidade de aviso prévio:</p>
<ul>
<li><strong>a)</strong> Emitir advertência formal ao Parceiro;</li>
<li><strong>b)</strong> Suspender temporariamente o acesso ao Programa;</li>
<li><strong>c)</strong> Reter comissões para análise e apuração;</li>
<li><strong>d)</strong> Cancelar comissões pendentes, aprovadas ou já pagas;</li>
<li><strong>e)</strong> Excluir definitivamente o Parceiro do Programa;</li>
<li><strong>f)</strong> Adotar medidas judiciais cabíveis para reparação de danos.</li>
</ul>

<h4>11.2. FRAUDE</h4>
<p><strong>11.2.1.</strong> Em caso de fraude comprovada ou suspeita fundamentada de fraude, a Lab Resumos poderá reter imediatamente todas as comissões do Parceiro, independentemente do status (pendentes, aprovadas ou pagas).</p>
<p><strong>11.2.2.</strong> As comissões retidas em decorrência de fraude serão utilizadas para compensação parcial dos danos causados à Lab Resumos.</p>
<p><strong>11.2.3.</strong> Em caso de fraude comprovada, o Parceiro perde o direito a todas as comissões, inclusive aquelas já aprovadas mas ainda não pagas.</p>
<p><strong>11.2.4.</strong> A Lab Resumos se reserva o direito de comunicar práticas fraudulentas às autoridades competentes e de buscar reparação integral dos danos causados por vias judiciais.</p>
<p><strong>11.3.</strong> As sanções previstas nesta cláusula podem ser aplicadas de forma isolada ou cumulativa, conforme a gravidade da infração.</p>',
            ],
            [
                'id' => 'rescisao',
                'title' => '12. Rescisão',
                'content' => '<p><strong>12.1.</strong> O Parceiro pode solicitar o encerramento de sua participação no Programa a qualquer tempo, mediante aviso prévio de 10 dias, através do Painel ou por e-mail para parceiros@labresumos.com.br.</p>
<p><strong>12.2.</strong> A Lab Resumos pode rescindir este acordo e encerrar a participação do Parceiro a qualquer momento, com ou sem justa causa, mediante comunicação por e-mail.</p>
<p><strong>12.3.</strong> Em caso de rescisão por justa causa (violação destes Termos, fraude, conduta inadequada), a exclusão será imediata e sem aviso prévio.</p>

<h4>12.4. EFEITOS DA RESCISÃO</h4>
<p><strong>12.4.1.</strong> Após a rescisão, o Parceiro perderá acesso ao Painel, cupom, link de referência e demais ferramentas do Programa.</p>
<p><strong>12.4.2.</strong> Em rescisão sem justa causa, as comissões aprovadas até a data da rescisão serão pagas conforme o calendário normal de pagamentos, desde que atingido o valor mínimo para saque.</p>
<p><strong>12.4.3.</strong> Em rescisão por justa causa decorrente de fraude, todas as comissões pendentes e aprovadas serão canceladas.</p>
<p><strong>12.4.4.</strong> A estrutura de rede do Parceiro será reorganizada, podendo os parceiros indicados ser realocados conforme critérios da Lab Resumos.</p>',
            ],
            [
                'id' => 'alteracoes',
                'title' => '13. Alterações nos Termos e no Programa',
                'content' => '<p><strong>13.1.</strong> A Lab Resumos pode modificar estes Termos e Condições a qualquer momento, a seu exclusivo critério.</p>
<p><strong>13.2.</strong> As alterações serão comunicadas por e-mail e/ou publicadas no Painel do Parceiro com antecedência mínima de 15 dias para entrada em vigor, exceto em casos de urgência ou determinação legal.</p>
<p><strong>13.3.</strong> A continuidade da participação no Programa após a entrada em vigor das alterações implica aceitação automática dos novos termos.</p>
<p><strong>13.4.</strong> Caso o Parceiro não concorde com as alterações, deverá solicitar sua exclusão do Programa antes da entrada em vigor das novas condições.</p>
<p><strong>13.5.</strong> A Lab Resumos pode, a qualquer momento e a seu exclusivo critério, alterar:</p>
<ul>
<li><strong>a)</strong> Os percentuais de comissão;</li>
<li><strong>b)</strong> Os produtos elegíveis ao Programa;</li>
<li><strong>c)</strong> Os valores mínimos para saque;</li>
<li><strong>d)</strong> A periodicidade de pagamentos;</li>
<li><strong>e)</strong> Os requisitos de atividade para comissões de rede;</li>
<li><strong>f)</strong> A validade do cookie de rastreamento;</li>
<li><strong>g)</strong> Os percentuais de desconto dos cupons;</li>
<li><strong>h)</strong> O período de carência das vendas;</li>
<li><strong>i)</strong> Quaisquer outras condições comerciais ou operacionais do Programa.</li>
</ul>',
            ],
            [
                'id' => 'limitacao',
                'title' => '14. Limitação de Responsabilidade',
                'content' => '<p><strong>14.1.</strong> A Lab Resumos não garante valores mínimos de comissão, número de vendas ou qualquer resultado financeiro ao Parceiro.</p>
<p><strong>14.2.</strong> A Lab Resumos não se responsabiliza por decisões comerciais, investimentos, despesas ou qualquer prejuízo do Parceiro relacionado à sua participação no Programa.</p>
<p><strong>14.3.</strong> A Lab Resumos não se responsabiliza por indisponibilidades temporárias do sistema, falhas técnicas ou interrupções dos serviços.</p>
<p><strong>14.4.</strong> A responsabilidade total da Lab Resumos perante o Parceiro está limitada ao valor das comissões efetivamente devidas e não pagas.</p>',
            ],
            [
                'id' => 'lgpd',
                'title' => '15. Proteção de Dados',
                'content' => '<p><strong>15.1.</strong> O Parceiro declara estar ciente de que seus dados pessoais serão tratados em conformidade com a Lei Geral de Proteção de Dados (Lei 13.709/2018).</p>
<p><strong>15.2.</strong> Os dados fornecidos pelo Parceiro serão utilizados exclusivamente para gestão do Programa, processamento de pagamentos e comunicações relacionadas.</p>
<p><strong>15.3.</strong> Para mais informações sobre o tratamento de dados, consulte a Política de Privacidade disponível em <a href="https://labresumos.com.br/privacidade" target="_blank">labresumos.com.br/privacidade</a>.</p>',
            ],
            [
                'id' => 'disposicoes',
                'title' => '16. Disposições Gerais',
                'content' => '<p><strong>16.1.</strong> A tolerância ou omissão da Lab Resumos em exigir o cumprimento de qualquer disposição destes Termos não constituirá renúncia ou novação.</p>
<p><strong>16.2.</strong> Se qualquer disposição destes Termos for considerada inválida ou inexequível, as demais disposições permanecerão em pleno vigor e efeito.</p>
<p><strong>16.3.</strong> Estes Termos constituem o acordo integral entre as partes em relação ao Programa, substituindo quaisquer entendimentos, propostas ou acordos anteriores.</p>
<p><strong>16.4.</strong> O Parceiro não pode ceder ou transferir sua participação no Programa sem autorização prévia e expressa da Lab Resumos.</p>',
            ],
            [
                'id' => 'foro',
                'title' => '17. Foro',
                'content' => '<p><strong>17.1.</strong> Fica eleito o foro da Comarca de Barueri, Estado de São Paulo, para dirimir quaisquer questões oriundas destes Termos, com renúncia expressa a qualquer outro, por mais privilegiado que seja.</p>',
            ],
            [
                'id' => 'contato',
                'title' => '18. Contato',
                'content' => '<p>Para dúvidas, esclarecimentos ou solicitações relacionadas ao Programa de Parceiros:</p>
<ul>
<li><strong>E-mail:</strong> <a href="mailto:parceiros@labresumos.com.br">parceiros@labresumos.com.br</a></li>
<li><strong>E-mail Financeiro:</strong> <a href="mailto:financeiro@labresumos.com.br">financeiro@labresumos.com.br</a></li>
</ul>
<p style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;"><em>Ao clicar em "Aceito os Termos e Condições" ou ao utilizar as ferramentas do Programa, o Parceiro declara ter lido, compreendido e concordado integralmente com todas as disposições aqui estabelecidas.</em></p>',
            ],
        ];
    }

    /**
     * Verifica se o afiliado aceitou a versão atual dos termos
     *
     * @param int $affiliate_id
     * @return bool
     */
    public function has_accepted_current($affiliate_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_acceptances';
        
        // Verifica se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if (!$table_exists) {
            return false;
        }
        
        $current_version = $this->get_current_version();
        
        $acceptance = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE affiliate_id = %d AND version = %s",
            $affiliate_id,
            $current_version
        ));
        
        return !empty($acceptance);
    }

    /**
     * Registra aceite dos termos
     *
     * @param int $affiliate_id
     * @param string $version
     * @return bool
     */
    public function record_acceptance($affiliate_id, $version = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_acceptances';
        
        if (!$version) {
            $version = $this->get_current_version();
        }
        
        // Verifica se já aceitou
        if ($this->has_accepted_current($affiliate_id)) {
            return true;
        }
        
        $affiliate = new LRP_Affiliate($affiliate_id);
        if (!$affiliate->exists()) {
            return false;
        }
        
        $user_ip = $this->get_user_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        $result = $wpdb->insert($table, [
            'affiliate_id' => $affiliate_id,
            'version' => $version,
            'accepted_at' => current_time('mysql'),
            'ip_address' => $user_ip,
            'user_agent' => $user_agent,
        ]);
        
        if ($result) {
            // Dispara ação para enviar emails
            do_action('lrp_terms_accepted', $affiliate, $version);
            
            // Envia emails
            $this->send_acceptance_emails($affiliate, $version);
            
            lrp_log('Termos aceitos', [
                'affiliate_id' => $affiliate_id,
                'version' => $version,
                'ip' => $user_ip,
            ], 'info');
            
            return true;
        }
        
        return false;
    }

    /**
     * Envia emails de confirmação de aceite
     *
     * @param LRP_Affiliate $affiliate
     * @param string $version
     */
    private function send_acceptance_emails($affiliate, $version) {
        $email_manager = LRP_Email_Manager::instance();
        $settings = LRP_Settings::instance();
        
        // Email para o afiliado
        $subject_affiliate = __('✅ Confirmação de Aceite dos Termos - Programa de Parceiros', 'lab-resumos-parceiros');
        $content_affiliate = $this->get_acceptance_email_content($affiliate, $version, 'affiliate');
        $this->send_email($affiliate->get_email(), $subject_affiliate, $content_affiliate);
        
        // Email para o admin
        $admin_email = $settings->get_admin_email();
        if ($admin_email) {
            $subject_admin = sprintf(
                __('📋 Termos aceitos por %s', 'lab-resumos-parceiros'),
                $affiliate->get_display_name()
            );
            $content_admin = $this->get_acceptance_email_content($affiliate, $version, 'admin');
            $this->send_email($admin_email, $subject_admin, $content_admin);
        }
    }

    /**
     * Retorna conteúdo do email de aceite
     *
     * @param LRP_Affiliate $affiliate
     * @param string $version
     * @param string $type 'affiliate' ou 'admin'
     * @return string
     */
    private function get_acceptance_email_content($affiliate, $version, $type) {
        $terms = $this->get_terms($version);
        $acceptance_date = current_time('d/m/Y H:i:s');
        $ip = $this->get_user_ip();
        
        if ($type === 'affiliate') {
            ob_start();
            ?>
            <div style="font-family: Arial, sans-serif; line-height: 1.6;">
                <p>Olá, <strong><?php echo esc_html($affiliate->get_display_name()); ?></strong>!</p>
                
                <p>Este email confirma que você aceitou os <strong>Termos e Condições do Programa de Parceiros Lab Resumos</strong>.</p>
                
                <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h4 style="margin-top: 0; color: #2A6B9F;">📋 Detalhes do Aceite</h4>
                    <table style="width: 100%; font-size: 14px;">
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Versão dos Termos:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($version); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Data/Hora:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($acceptance_date); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>IP:</strong></td>
                            <td style="padding: 8px 0;"><?php echo esc_html($ip); ?></td>
                        </tr>
                    </table>
                </div>
                
                <p>Guarde este email como comprovante do seu aceite. Você pode consultar os termos completos a qualquer momento em seu painel de parceiro.</p>
                
                <p style="margin-top: 30px;">Atenciosamente,<br><strong>Equipe Lab Resumos</strong></p>
            </div>
            <?php
            return ob_get_clean();
        } else {
            // Email para admin
            ob_start();
            ?>
            <div style="font-family: Arial, sans-serif; line-height: 1.6;">
                <p>Um afiliado aceitou os termos do programa.</p>
                
                <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h4 style="margin-top: 0; color: #2A6B9F;">👤 Dados do Afiliado</h4>
                    <table style="width: 100%; font-size: 14px;">
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Nome:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($affiliate->get_display_name()); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Email:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($affiliate->get_email()); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Cupom:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($affiliate->get_coupon_code()); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Versão dos Termos:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($version); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><strong>Data/Hora:</strong></td>
                            <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef;"><?php echo esc_html($acceptance_date); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>IP:</strong></td>
                            <td style="padding: 8px 0;"><?php echo esc_html($ip); ?></td>
                        </tr>
                    </table>
                </div>
                
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=lrp-affiliates&action=view&id=' . $affiliate->get_id())); ?>" 
                       style="display: inline-block; background: #2A6B9F; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">
                        Ver Afiliado
                    </a>
                </p>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    /**
     * Envia email
     *
     * @param string $to
     * @param string $subject
     * @param string $content
     * @return bool
     */
    private function send_email($to, $subject, $content) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Lab Resumos <' . get_option('admin_email') . '>',
        ];
        
        $html = $this->wrap_email_html($subject, $content);
        
        return wp_mail($to, $subject, $html, $headers);
    }

    /**
     * Envolve conteúdo em HTML de email
     *
     * @param string $subject
     * @param string $content
     * @return string
     */
    private function wrap_email_html($subject, $content) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($subject) . '</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f4f4f4;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                            <tr>
                                <td style="background-color: #2A6B9F; padding: 30px; text-align: center;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Lab Resumos</h1>
                                    <p style="color: #ffffff; margin: 10px 0 0 0; opacity: 0.9;">Programa de Parceiros</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 30px;">
                                    ' . $content . '
                                </td>
                            </tr>
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666;">
                                    <p style="margin: 0;">&copy; ' . date('Y') . ' Lab Resumos. Todos os direitos reservados.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }

    /**
     * Cria nova versão dos termos
     *
     * @param array $data
     * @return int|false ID da nova versão ou false
     */
    public function create_version($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_versions';
        
        // Desativa versões anteriores
        $wpdb->update($table, ['is_active' => 0], ['is_active' => 1]);
        
        $result = $wpdb->insert($table, [
            'version' => sanitize_text_field($data['version']),
            'title' => sanitize_text_field($data['title']),
            'intro' => wp_kses_post($data['intro']),
            'sections' => wp_json_encode($data['sections']),
            'changelog' => isset($data['changelog']) ? wp_kses_post($data['changelog']) : '',
            'is_active' => 1,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);
        
        if ($result) {
            $new_id = $wpdb->insert_id;
            
            // Atualiza opção de versão atual
            update_option('lrp_terms_current_version', $data['version']);
            
            // Dispara ação para notificar afiliados
            do_action('lrp_terms_version_updated', $data['version'], $data['changelog'] ?? '');
            
            lrp_log('Nova versão de termos criada', [
                'version' => $data['version'],
                'created_by' => get_current_user_id(),
            ], 'info');
            
            return $new_id;
        }
        
        return false;
    }

    /**
     * Notifica afiliados sobre novos termos
     *
     * @param string $version
     * @param string $changelog
     */
    public function notify_affiliates_new_terms($version, $changelog = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_affiliates';
        
        // Busca todos os afiliados ativos
        $affiliates = $wpdb->get_results(
            "SELECT id FROM $table WHERE status = 'active'",
            ARRAY_A
        );
        
        if (empty($affiliates)) {
            return;
        }
        
        $terms = $this->get_terms($version);
        $terms_page = get_option('lrp_terms_page_id');
        $terms_url = $terms_page ? get_permalink($terms_page) : '';
        
        foreach ($affiliates as $aff) {
            $affiliate = new LRP_Affiliate($aff['id']);
            if (!$affiliate->exists()) {
                continue;
            }
            
            // Adiciona notificação no sistema
            $this->add_notification($affiliate->get_id(), $version);
            
            // Envia email
            $subject = __('📋 Atualização nos Termos do Programa de Parceiros', 'lab-resumos-parceiros');
            $content = $this->get_new_version_email_content($affiliate, $version, $changelog, $terms_url);
            $this->send_email($affiliate->get_email(), $subject, $content);
        }
        
        lrp_log('Afiliados notificados sobre nova versão', [
            'version' => $version,
            'total_affiliates' => count($affiliates),
        ], 'info');
    }

    /**
     * Retorna conteúdo do email de nova versão
     *
     * @param LRP_Affiliate $affiliate
     * @param string $version
     * @param string $changelog
     * @param string $terms_url
     * @return string
     */
    private function get_new_version_email_content($affiliate, $version, $changelog, $terms_url) {
        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; line-height: 1.6;">
            <p>Olá, <strong><?php echo esc_html($affiliate->get_display_name()); ?></strong>!</p>
            
            <p>Informamos que os <strong>Termos e Condições do Programa de Parceiros Lab Resumos</strong> foram atualizados para a versão <strong><?php echo esc_html($version); ?></strong>.</p>
            
            <?php if (!empty($changelog)) : ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0;">
                <h4 style="margin-top: 0; color: #856404;">📝 O que mudou:</h4>
                <div style="color: #856404;"><?php echo wp_kses_post($changelog); ?></div>
            </div>
            <?php endif; ?>
            
            <div style="background: #e7f3ff; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <p style="margin: 0;"><strong>⚠️ Ação necessária:</strong></p>
                <p style="margin: 10px 0 0 0;">Para continuar participando do programa, você precisa ler e aceitar os novos termos.</p>
            </div>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="<?php echo esc_url($terms_url); ?>" 
                   style="display: inline-block; background: #2A6B9F; color: white; padding: 15px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 16px;">
                    📋 Ver e Aceitar Novos Termos
                </a>
            </p>
            
            <p style="margin-top: 30px;">Atenciosamente,<br><strong>Equipe Lab Resumos</strong></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Adiciona notificação para o afiliado
     *
     * @param int $affiliate_id
     * @param string $version
     */
    private function add_notification($affiliate_id, $version) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_notifications';
        
        $wpdb->insert($table, [
            'affiliate_id' => $affiliate_id,
            'version' => $version,
            'type' => 'new_version',
            'is_read' => 0,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Retorna notificações de termos pendentes
     *
     * @param int $affiliate_id
     * @return array
     */
    public function get_pending_notifications($affiliate_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_notifications';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE affiliate_id = %d AND is_read = 0 ORDER BY created_at DESC",
            $affiliate_id
        ), ARRAY_A);
    }

    /**
     * Marca notificação como lida
     *
     * @param int $notification_id
     * @return bool
     */
    public function mark_notification_read($notification_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_notifications';
        
        return $wpdb->update($table, ['is_read' => 1], ['id' => $notification_id]) !== false;
    }

    /**
     * Verifica termos pendentes antes de renderizar dashboard
     *
     * @param LRP_Affiliate $affiliate
     */
    public function check_pending_terms($affiliate) {
        // Este método pode ser usado para forçar aceite de termos
    }

    /**
     * Retorna histórico de aceites de um afiliado
     *
     * @param int $affiliate_id
     * @return array
     */
    public function get_acceptance_history($affiliate_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_acceptances';
        
        // Verifica se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if (!$table_exists) {
            return [];
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE affiliate_id = %d ORDER BY accepted_at DESC",
            $affiliate_id
        ), ARRAY_A);
        
        return $results ?: [];
    }

    /**
     * Retorna histórico de todas as versões
     *
     * @return array
     */
    public function get_version_history() {
        global $wpdb;
        $table = $wpdb->prefix . 'lrp_terms_versions';
        
        // Verifica se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if (!$table_exists) {
            return [];
        }
        
        $versions = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY created_at DESC",
            ARRAY_A
        );
        
        if (!$versions) {
            return [];
        }
        
        foreach ($versions as &$v) {
            $v['sections'] = json_decode($v['sections'], true);
        }
        
        return $versions;
    }

    /**
     * Retorna IP do usuário
     *
     * @return string
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
        }
    }

    /**
     * Retorna estatísticas de aceite
     *
     * @param string $version
     * @return array
     */
    public function get_acceptance_stats($version = null) {
        global $wpdb;
        $acceptances_table = $wpdb->prefix . 'lrp_terms_acceptances';
        $affiliates_table = $wpdb->prefix . 'lrp_affiliates';
        
        if (!$version) {
            $version = $this->get_current_version();
        }
        
        // Verifica se as tabelas existem
        $acceptances_exists = $wpdb->get_var("SHOW TABLES LIKE '$acceptances_table'") === $acceptances_table;
        
        if (!$acceptances_exists) {
            return [
                'version' => $version,
                'total_active' => 0,
                'total_accepted' => 0,
                'total_pending' => 0,
                'acceptance_rate' => 0,
            ];
        }
        
        // Total de afiliados ativos
        $total_active = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $affiliates_table WHERE status = 'active'"
        );
        
        // Total que aceitou a versão
        $total_accepted = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT a.affiliate_id) 
             FROM $acceptances_table a
             INNER JOIN $affiliates_table af ON a.affiliate_id = af.id
             WHERE a.version = %s AND af.status = 'active'",
            $version
        ));
        
        return [
            'version' => $version,
            'total_active' => $total_active,
            'total_accepted' => $total_accepted,
            'total_pending' => $total_active - $total_accepted,
            'acceptance_rate' => $total_active > 0 ? round(($total_accepted / $total_active) * 100, 1) : 0,
        ];
    }
}
