<?php
/**
 * Plugin Name: Gravity Vindi
 * Description: Plugin para integração entre Gravity Forms e API da Vindi. Cria clientes automaticamente e redireciona para checkout com dados pré-preenchidos.
 * Version: 2.4.0
 * Author: <a href="http://cirujanagrafica.com/">Cirujana Gráfica</a>
 * License: GPL2
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Text Domain: gravity-vindi
 */

// Impede acesso direto ao arquivo
if (!defined('ABSPATH')) {
	exit('Acesso direto não permitido.');
}

// Constantes do plugin
define('GRAVITY_VINDI_VERSION', '2.4.0');
define('GRAVITY_VINDI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GRAVITY_VINDI_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Classe principal do plugin Gravity Vindi
 */
class GravityVindi {
	
	/**
	 * URLs da API Vindi
	 */
	private const API_URLS = [
		'production' => 'https://app.vindi.com.br/api/v1',
		'sandbox' => 'https://sandbox-app.vindi.com.br/api/v1'
	];
	
	/**
	 * Inicializa o plugin
	 */
	public function __construct() {
		add_action('init', [$this, 'init']);
		add_action('admin_init', [$this, 'admin_init']);
		add_action('admin_menu', [$this, 'admin_menu']);
		
		// Hook para o formulário ID 2
		add_filter('gform_confirmation_2', [$this, 'handle_form_submission'], 100, 4);
		
		// Link de configuração na página de plugins
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
	}
	
	/**
	 * Inicialização do plugin
	 */
	public function init() {
		// Verifica se Gravity Forms está ativo
		if (!class_exists('GFForms')) {
			add_action('admin_notices', [$this, 'gravity_forms_required_notice']);
			return;
		}
		
		// Carrega textdomain para traduções
		load_plugin_textdomain('gravity-vindi', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}
	
	/**
	 * Inicialização do admin
	 */
	public function admin_init() {
		$this->register_settings();
	}
	
	/**
	 * Adiciona link de configuração
	 */
	public function add_action_links($links) {
		$config_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url('options-general.php?page=gravity-vindi'),
			__('Configurações', 'gravity-vindi')
		);
		array_unshift($links, $config_link);
		return $links;
	}
	
	/**
	 * Adiciona menu de administração
	 */
	public function admin_menu() {
		add_options_page(
			__('Configurações Gravity Vindi', 'gravity-vindi'),
			__('Gravity Vindi', 'gravity-vindi'),
			'manage_options',
			'gravity-vindi',
			[$this, 'admin_page']
		);
	}
	
	/**
	 * Registra configurações (SISTEMA ORIGINAL)
	 */
	private function register_settings() {
		register_setting('gravity_vindi_settings', 'gravity_vindi_api_key');
		register_setting('gravity_vindi_settings', 'gravity_vindi_use_production');
		register_setting('gravity_vindi_settings', 'gravity_vindi_page_code');
		register_setting('gravity_vindi_settings', 'gravity_vindi_jwt_secret');
		
		add_settings_section('gravity_vindi_main', 'Configurações da API', null, 'gravity_vindi');
		
		add_settings_field(
			'gravity_vindi_api_key_field',
			'API Key:',
			function () {
				$value = esc_attr(get_option('gravity_vindi_api_key'));
				echo "<input type='text' name='gravity_vindi_api_key' value='$value' size='50' />";
			},
			'gravity_vindi',
			'gravity_vindi_main'
		);
		
		add_settings_field(
			'gravity_vindi_page_code_field',
			'Código da Página de Pagamento:',
			function () {
				$value = esc_attr(get_option('gravity_vindi_page_code'));
				echo "<input type='text' name='gravity_vindi_page_code' value='$value' size='50' />";
				echo "<p class='description'>Código da página de pagamento criada no painel Vindi</p>";
			},
			'gravity_vindi',
			'gravity_vindi_main'
		);
		
		add_settings_field(
			'gravity_vindi_jwt_secret_field',
			'Chave Secreta JWT:',
			function () {
				$value = esc_attr(get_option('gravity_vindi_jwt_secret'));
				echo "<input type='password' name='gravity_vindi_jwt_secret' value='$value' size='50' />";
				echo "<p class='description'>Chave secreta para assinatura do JWT (obtida no painel Vindi)</p>";
			},
			'gravity_vindi',
			'gravity_vindi_main'
		);
		
		add_settings_field(
			'gravity_vindi_use_production_field',
			'Ambiente:',
			function () {
				$value = get_option('gravity_vindi_use_production');
				echo "<label><input type='checkbox' name='gravity_vindi_use_production' value='1' " . checked(1, $value, false) . " /> Ativar ambiente de produção</label>";
			},
			'gravity_vindi',
			'gravity_vindi_main'
		);
	}
	
	/**
	 * Página de administração
	 */
	public function admin_page() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Você não tem permissão para acessar esta página.'));
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields('gravity_vindi_settings');
				do_settings_sections('gravity_vindi');
				submit_button();
				?>
			</form>
			
			<!-- Status da configuração -->
			<div class="card" style="margin-top: 20px;">
				<h2><?php _e('Status da Configuração', 'gravity-vindi'); ?></h2>
				<?php $this->display_config_status(); ?>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Exibe status da configuração
	 */
	private function display_config_status() {
		$required_fields = [
			'gravity_vindi_api_key' => 'API Key',
			'gravity_vindi_page_code' => 'Page Code', 
			'gravity_vindi_jwt_secret' => 'JWT Secret'
		];
		
		echo '<ul>';
		foreach ($required_fields as $field => $label) {
			$value = get_option($field);
			$status = !empty($value) ? '✅' : '❌';
			echo "<li>{$status} {$label}</li>";
		}
		
		$environment = get_option('gravity_vindi_use_production') ? 'Produção' : 'Sandbox';
		echo "<li>🔧 Ambiente: {$environment}</li>";
		echo '</ul>';
	}
	
	/**
	 * Aviso de Gravity Forms necessário
	 */
	public function gravity_forms_required_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			__('O plugin Gravity Vindi requer o Gravity Forms para funcionar.', 'gravity-vindi')
		);
	}
	
	/**
	 * Processa submissão do formulário
	 */
	public function handle_form_submission($confirmation, $form, $entry, $ajax) {
		try {
			// Validação inicial
			if (!$this->validate_configuration()) {
				return $this->get_fallback_redirect();
			}
			
			// Extrai dados do formulário
			$form_data = $this->extract_form_data($entry);
			if (!$form_data) {
				return $this->get_fallback_redirect();
			}
			
			// Busca ou cria cliente
			$customer_code = $this->find_or_create_customer($form_data);
			if (!$customer_code) {
				return $this->get_fallback_redirect();
			}
			
			// Gera URL de pagamento com JWT
			$payment_url = $this->build_payment_url($customer_code);
			
			return [
				'redirect' => $payment_url,
				'message' => '<script>window.location.href="' . esc_url($payment_url) . '";</script>' .
						   '<p>' . __('Redirecionando para pagamento...', 'gravity-vindi') . '</p>'
			];
			
		} catch (Exception $e) {
			return $this->get_fallback_redirect();
		}
	}
	
	/**
	 * Valida configuração
	 */
	private function validate_configuration() {
		$required = ['gravity_vindi_api_key', 'gravity_vindi_page_code', 'gravity_vindi_jwt_secret'];
		
		foreach ($required as $field) {
			if (empty(get_option($field))) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Extrai dados do formulário
	 */
	private function extract_form_data($entry) {
		$nome = sanitize_text_field($entry[105] ?? '');      // ID 105
		$sobrenome = sanitize_text_field($entry[106] ?? ''); // ID 106
		$email = sanitize_email($entry[1] ?? '');            // ID 1
		$cpf = sanitize_text_field($entry[14] ?? '');        // ID 14
		
		// Nome completo obrigatório
		$nome_completo = trim($nome . ' ' . $sobrenome);
		
		if (empty($nome_completo) || empty($email)) {
			return false;
		}
		
		$data = [
			'nome_completo' => $nome_completo,
			'email' => $email
		];
		
		// CPF opcional mas limpo
		if (!empty($cpf)) {
			$data['cpf'] = preg_replace('/[^0-9]/', '', $cpf);
		}
		
		return $data;
	}
	
	/**
	 * Busca ou cria cliente
	 */
	private function find_or_create_customer($form_data) {
		// Primeiro tenta buscar cliente existente
		$existing_customer = $this->find_customer_by_email($form_data['email']);
		
		if ($existing_customer) {
			return $existing_customer['code'];
		}
		
		// Se não encontrou, cria novo
		return $this->create_customer($form_data);
	}
	
	/**
	 * Busca cliente por email
	 */
	private function find_customer_by_email($email) {
		$api_url = $this->get_api_url() . '/customers';
		$url = add_query_arg('query', 'email:' . urlencode($email), $api_url);
		
		$response = $this->make_api_request($url, 'GET');
		
		if ($response && !empty($response['customers']) && is_array($response['customers'])) {
			return $response['customers'][0];
		}
		
		return false;
	}
	
	/**
	 * Cria novo cliente
	 */
	private function create_customer($form_data) {
		$customer_data = [
			'name' => $form_data['nome_completo'],
			'email' => $form_data['email'],
			'code' => 'amc-' . time()
		];
		
		// Adiciona CPF se disponível
		if (isset($form_data['cpf'])) {
			$customer_data['registry_code'] = $form_data['cpf'];
		}
		
		$response = $this->make_api_request(
			$this->get_api_url() . '/customers',
			'POST',
			$customer_data
		);
		
		if ($response && isset($response['customer']['code'])) {
			return $response['customer']['code'];
		}
		
		return false;
	}
	
	/**
	 * Faz requisição para API
	 */
	private function make_api_request($url, $method = 'GET', $data = null) {
		$api_key = get_option('gravity_vindi_api_key');
		
		$args = [
			'method' => $method,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
				'Accept' => 'application/json',
				'User-Agent' => 'Gravity-Vindi/' . GRAVITY_VINDI_VERSION
			],
			'timeout' => 30
		];
		
		if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode($data);
		}
		
		$response = wp_remote_request($url, $args);
		
		if (is_wp_error($response)) {
			return false;
		}
		
		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		
		if ($status_code >= 200 && $status_code < 300) {
			return json_decode($body, true);
		}
		
		return false;
	}
	
	/**
	 * Constrói URL de pagamento com JWT
	 */
	private function build_payment_url($customer_code) {
		$use_production = get_option('gravity_vindi_use_production');
		$page_code = get_option('gravity_vindi_page_code', '04a304d8-4ad2-428e-bb4c-5bf5a96491ac');
		
		$base_url = $use_production ? 
			"https://app.vindi.com.br/customer/pages/{$page_code}/subscriptions/new" :
			"https://sandbox-app.vindi.com.br/customer/pages/{$page_code}/subscriptions/new";
		
		// Gera JWT
		$jwt = $this->generate_jwt(['customer_code' => $customer_code]);
		
		return $jwt ? $base_url . '?payload=' . urlencode($jwt) : $base_url;
	}
	
	/**
	 * Gera JWT
	 */
	private function generate_jwt($payload) {
		$secret = get_option('gravity_vindi_jwt_secret');
		
		if (empty($secret)) {
			return false;
		}
		
		$header = wp_json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
		$payload = wp_json_encode($payload);
		
		$headerEncoded = $this->base64url_encode($header);
		$payloadEncoded = $this->base64url_encode($payload);
		
		$signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $secret, true);
		$signatureEncoded = $this->base64url_encode($signature);
		
		return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
	}
	
	/**
	 * Base64 URL encode
	 */
	private function base64url_encode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}
	
	/**
	 * Retorna redirect de fallback
	 */
	private function get_fallback_redirect() {
		$use_production = get_option('gravity_vindi_use_production');
		$page_code = get_option('gravity_vindi_page_code', '04a304d8-4ad2-428e-bb4c-5bf5a96491ac');
		
		$url = $use_production ?
			"https://app.vindi.com.br/customer/pages/{$page_code}/subscriptions/new" :
			"https://sandbox-app.vindi.com.br/customer/pages/{$page_code}/subscriptions/new";
		
		return [
			'redirect' => $url,
			'message' => '<script>window.location.href="' . esc_url($url) . '";</script>' .
					   '<p>' . __('Redirecionando para pagamento...', 'gravity-vindi') . '</p>'
		];
	}
	
	/**
	 * Obtém URL da API
	 */
	private function get_api_url() {
		$use_production = get_option('gravity_vindi_use_production');
		return $use_production ? self::API_URLS['production'] : self::API_URLS['sandbox'];
	}
}

// Inicializa o plugin
new GravityVindi();