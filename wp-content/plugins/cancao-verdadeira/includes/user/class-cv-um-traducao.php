<?php
// cancao-verdadeira/includes/user/class-cv-um-traducao.php
// Criado em: 24/09/2026 (plugin v2.40.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Traduz para o português os textos do Ultimate Member que aparecem nas
// telas Entrar, Criar conta, Minha conta, Perfil e Recuperar senha.
// Motivo: o WordPress.org não tem pacote pt_BR do Ultimate Member (conferido
// em 24/09/2026: 31 idiomas, nenhum pt_BR), então tudo aparecia em inglês.
// 1) Filtro gettext só no domínio "ultimate-member" (lista abaixo).
// 2) Uma vez só: troca os rótulos em inglês gravados nos formulários de login
//    e cadastro (_um_custom_fields) e os assuntos dos e-mails (um_options),
//    sem mexer em textos já personalizados. Corpo dos e-mails: tema,
//    pasta cv-child/ultimate-member/email/.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_UM_Traducao {

    const VERSAO_ROTULOS = 2; // sobe quando a lista de rótulos/opções mudar (v2: assuntos dos e-mails)

    // Texto original do Ultimate Member => texto em português.
    private static $textos = array(
        // Entrar / Criar conta / Recuperar senha
        'Username or E-mail'   => 'Usuário ou e-mail',
        'Username or Email'    => 'Usuário ou e-mail',
        'Username'             => 'Nome de usuário',
        'Password'             => 'Senha',
        'Keep me signed in'    => 'Manter conectado',
        'Login'                => 'Entrar',
        'Register'             => 'Criar conta',
        'Forgot your password?' => 'Esqueceu a senha?',
        'First Name'           => 'Nome',
        'Last Name'            => 'Sobrenome',
        'E-mail Address'       => 'E-mail',
        'Email Address'        => 'E-mail',
        'Confirm Password'     => 'Confirme a senha',
        'Confirm %s'           => 'Confirmar %s',
        'Reset password'       => 'Redefinir senha',
        'Reset Password'       => 'Redefinir senha',
        'To reset your password, please enter your email address or username below.' => 'Para redefinir sua senha, digite abaixo seu e-mail ou nome de usuário.',
        'Enter your username or email' => 'Digite seu usuário ou e-mail',
        'If an account matching the provided details exists, we will send a password reset link. Please check your inbox.' => 'Se houver uma conta com esses dados, enviaremos um link para redefinir a senha. Confira sua caixa de entrada.',
        'Your password reset link appears to be invalid. Please request a new link below.' => 'O link para redefinir a senha parece inválido. Peça um novo link abaixo.',
        'Your password reset link has expired. Please request a new link below.' => 'O link para redefinir a senha expirou. Peça um novo link abaixo.',

        // Mensagens de erro e de sucesso
        'Please enter your username or email' => 'Digite seu usuário ou e-mail',
        'Please provide your username or email' => 'Digite seu usuário ou e-mail',
        'Please enter your username'     => 'Digite seu nome de usuário',
        'Please enter your email'        => 'Digite seu e-mail',
        'Please enter your password'     => 'Digite sua senha',
        'Please confirm your password'   => 'Confirme sua senha',
        'Please provide a valid email'   => 'Digite um e-mail válido',
        'Password is incorrect. Please try again.' => 'Senha incorreta. Tente de novo.',
        'The email you entered is incorrect'    => 'O e-mail digitado não está correto',
        'The username you entered is incorrect' => 'O nome de usuário digitado não está correto',
        'Your passwords do not match'    => 'As senhas não são iguais',
        'Your password must contain at least %d characters' => 'A senha precisa ter pelo menos %d caracteres',
        'Your password must contain less than %d characters' => 'A senha precisa ter menos de %d caracteres',
        'Your password must contain at least one capital letter'   => 'A senha precisa ter pelo menos uma letra maiúscula',
        'Your password must contain at least one lowercase letter' => 'A senha precisa ter pelo menos uma letra minúscula',
        'Your password must contain at least one number'           => 'A senha precisa ter pelo menos um número',
        'Your password cannot contain the part of your username'      => 'A senha não pode conter parte do seu nome de usuário',
        'Your password cannot contain the part of your email address' => 'A senha não pode conter parte do seu e-mail',
        'You must provide a username'    => 'Informe um nome de usuário',
        'You must provide a username or email' => 'Informe seu usuário ou e-mail',
        'You must provide your email'    => 'Informe seu e-mail',
        'You must provide your first name' => 'Informe seu nome',
        'You must provide your last name'  => 'Informe seu sobrenome',
        'You must enter a password'      => 'Digite uma senha',
        'You must enter your password'   => 'Digite sua senha',
        'You must enter a new password'  => 'Digite a nova senha',
        'You must confirm your new password' => 'Confirme a nova senha',
        'Username cannot be an email'    => 'O nome de usuário não pode ser um e-mail',
        'Your username contains invalid characters' => 'O nome de usuário tem caracteres não permitidos',
        'You are not allowed to use this word as your username.' => 'Esta palavra não pode ser usada como nome de usuário.',
        'This field is required'         => 'Este campo é obrigatório',
        'Required'                       => 'Obrigatório',
        'This field must contain at least %s characters' => 'Este campo precisa ter pelo menos %s caracteres',
        'This field must contain less than %s characters' => 'Este campo precisa ter menos de %s caracteres',
        'You are already registered.'    => 'Você já está cadastrado.',
        'Your account has been disabled.' => 'Sua conta foi desativada.',
        'Your account has not been approved yet.' => 'Sua conta ainda não foi aprovada.',
        'Your account is awaiting email verification.' => 'Sua conta aguarda a confirmação do e-mail.',
        'Your account is now active! You can login.' => 'Sua conta está ativa! Você já pode entrar.',
        'Your account is pending review' => 'Sua conta está em análise',
        'Your account was updated successfully.' => 'Sua conta foi atualizada.',
        'You have successfully changed password.' => 'Senha alterada com sucesso.',
        'You have successfully changed your password.' => 'Senha alterada com sucesso.',
        'This email address has been blocked.' => 'Este e-mail foi bloqueado.',

        // Minha conta
        'Account'              => 'Conta',
        'Your account'         => 'Sua conta',
        'Change Password'      => 'Trocar senha',
        'Privacy'              => 'Privacidade',
        'Delete Account'       => 'Excluir conta',
        'Current Password'     => 'Senha atual',
        'New Password'         => 'Nova senha',
        'Update Account'       => 'Salvar conta',
        'Update Password'      => 'Salvar nova senha',
        'Update Privacy'       => 'Salvar privacidade',
        'Profile Privacy'      => 'Quem pode ver meu perfil',
        'Everyone'             => 'Todos',
        'Only me'              => 'Só eu',
        'Avoid indexing my profile by search engines' => 'Não mostrar meu perfil no Google e em outros buscadores',
        'Hide my profile from directory' => 'Esconder meu perfil da lista de membros',
        'Show my last login?'  => 'Mostrar meu último acesso?',
        'Yes'                  => 'Sim',
        'No'                   => 'Não',
        'Download your data'   => 'Baixar meus dados',
        'Enter your current password to confirm a new export of your personal data.' => 'Digite sua senha atual para pedir uma cópia dos seus dados pessoais.',
        'Request data'         => 'Pedir meus dados',
        'Erase of your data'   => 'Apagar meus dados',
        'Enter your current password to confirm the erasure of your personal data.' => 'Digite sua senha atual para confirmar que quer apagar seus dados pessoais.',
        'Request data erase'   => 'Pedir para apagar meus dados',
        'Are you sure you want to delete your account? This will erase all of your account data from the site. To delete your account enter your password below.' => 'Tem certeza de que quer excluir sua conta? Todos os seus dados serão apagados do site. Para excluir, digite sua senha abaixo.',
        'View profile'         => 'Ver perfil',

        // Perfil
        'Edit Profile'         => 'Editar perfil',
        'My Account'           => 'Minha conta',
        'Logout'               => 'Sair',
        'About'                => 'Sobre',
        'Posts'                => 'Publicações',
        'Comments'             => 'Comentários',
        'You have not created any posts.' => 'Nenhuma publicação ainda.',
        'You have not made any comments.' => 'Nenhum comentário ainda.',
        'Upload a cover photo' => 'Enviar foto de capa',
        'Change your cover photo' => 'Trocar a foto de capa',
        'Change your profile photo' => 'Trocar a foto do perfil',
        'Upload photo'         => 'Enviar foto',
        'Remove photo'         => 'Remover foto',
        'Upload'               => 'Enviar',
        'Apply'                => 'Aplicar',
        'Cancel'               => 'Cancelar',
        'Update'               => 'Salvar',
        'Save'                 => 'Salvar',
        'Remove'               => 'Remover',
        'Delete'               => 'Excluir',
        'Select'               => 'Selecionar',
        'Back'                 => 'Voltar',
        'This user account status is %s' => 'Situação da conta: %s',
        'Approved'             => 'Aprovada',
        'Your profile is looking a little empty. Why not <a href="%s">add</a> some information!' => 'Seu perfil ainda está vazio. Que tal <a href="%s">adicionar</a> algumas informações?',
        'Please upload a valid image!' => 'Envie uma imagem válida!',
        'Sorry this is not a valid image.' => 'Este arquivo não é uma imagem válida.',
        'Sorry this is not a valid file.'  => 'Este arquivo não é válido.',

        // Botão do e-mail de boas-vindas ({action_title})
        'Login to our site'    => 'Entrar no site',
        'Set your password'    => 'Criar sua senha',
    );

    // Opções do Ultimate Member (assuntos dos e-mails e avisos): inglês => português.
    // Só troca se a opção ainda estiver com o texto original em inglês.
    private static $opcoes = array(
        'welcome_email_sub'       => array( 'Welcome to {site_name}!', 'Bem-vindo(a) ao {site_name}!' ),
        'checkmail_email_sub'     => array( 'Please activate your account', 'Ative sua conta no {site_name}' ),
        'resetpw_email_sub'       => array( 'Reset your password', 'Crie uma nova senha' ),
        'changedpw_email_sub'     => array( 'Your {site_name} password has been changed', 'Sua senha no {site_name} foi alterada' ),
        'changedaccount_email_sub'=> array( 'Your account at {site_name} was updated', 'Sua conta no {site_name} foi atualizada' ),
        'inactive_email_sub'      => array( 'Your account has been deactivated', 'Sua conta foi desativada' ),
        'deletion_email_sub'      => array( 'Your account has been deleted', 'Sua conta foi excluída' ),
        'pending_email_sub'       => array( '[{site_name}] New user account', '[{site_name}] Cadastro em análise' ),
        'approved_email_sub'      => array( 'Your account at {site_name} is now active', 'Sua conta no {site_name} está ativa' ),
        'rejected_email_sub'      => array( 'Your account has been rejected', 'Seu cadastro não foi aprovado' ),
        'delete_account_text'     => array( 'Are you sure you want to delete your account? This will erase all of your account data from the site. To delete your account enter your password below.', 'Tem certeza de que quer excluir sua conta? Todos os seus dados serão apagados do site. Para excluir, digite sua senha abaixo.' ),
        'delete_account_no_pass_required_text' => array( 'Are you sure you want to delete your account? This will erase all of your account data from the site. To delete your account, click on the button below.', 'Tem certeza de que quer excluir sua conta? Todos os seus dados serão apagados do site. Para excluir, clique no botão abaixo.' ),
    );

    // Rótulos gravados nos formulários (login/cadastro), em inglês => português.
    private static $rotulos = array(
        'Username or E-mail' => 'Usuário ou e-mail',
        'Username'           => 'Nome de usuário',
        'E-mail Address'     => 'E-mail',
        'Password'           => 'Senha',
        'First Name'         => 'Nome',
        'Last Name'          => 'Sobrenome',
    );

    public static function init() {
        add_filter( 'gettext', array( __CLASS__, 'traduzir' ), 20, 3 );
        add_action( 'init',    array( __CLASS__, 'traduzir_formularios' ), 30 );
    }

    public static function traduzir( $traduzido, $original, $dominio ) {
        if ( 'ultimate-member' !== $dominio ) { return $traduzido; }
        // Só troca quando ainda está em inglês (se um dia houver pacote pt_BR, ele manda).
        if ( $traduzido !== $original ) { return $traduzido; }
        return isset( self::$textos[ $original ] ) ? self::$textos[ $original ] : $traduzido;
    }

    /**
     * Uma vez por versão da lista: troca os rótulos em inglês dos formulários
     * do Ultimate Member. Rótulos já personalizados ficam como estão.
     */
    public static function traduzir_formularios() {
        if ( (int) get_option( 'cv_um_rotulos_pt', 0 ) >= self::VERSAO_ROTULOS ) { return; }
        $forms = get_option( 'um_core_forms', array() );
        foreach ( (array) $forms as $form_id ) {
            $campos = get_post_meta( (int) $form_id, '_um_custom_fields', true );
            if ( ! is_array( $campos ) ) { continue; }
            $mudou = false;
            foreach ( $campos as $chave => $campo ) {
                $rotulo = isset( $campo['label'] ) ? $campo['label'] : '';
                if ( isset( self::$rotulos[ $rotulo ] ) ) {
                    $campos[ $chave ]['label'] = self::$rotulos[ $rotulo ];
                    $mudou = true;
                }
            }
            if ( $mudou ) { update_post_meta( (int) $form_id, '_um_custom_fields', $campos ); }
        }

        // Assuntos dos e-mails e avisos gravados nas opções do Ultimate Member
        $um = get_option( 'um_options', array() );
        if ( is_array( $um ) ) {
            $mudou = false;
            foreach ( self::$opcoes as $chave => $par ) {
                if ( isset( $um[ $chave ] ) && $um[ $chave ] === $par[0] ) {
                    $um[ $chave ] = $par[1];
                    $mudou = true;
                }
            }
            if ( $mudou ) { update_option( 'um_options', $um ); }
        }
        update_option( 'cv_um_rotulos_pt', self::VERSAO_ROTULOS, false );
    }
}

CV_UM_Traducao::init();
