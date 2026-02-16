export type LoginMethod = '6_digit_numeric' | '6_char_alphanumeric' | '8_digit_numeric' | '8_char_alphanumeric' | 'magic_link';

export type OTPType = 'numeric' | 'alphanumeric';

export interface Config {
  login_method: LoginMethod;
  otp_length: number;
  otp_type: OTPType;
  expiry_seconds: number;
  site_name: string;
  terms_page_url: string | null;
  privacy_page_url: string | null;
  show_policy_links: boolean;
  hide_footer_credit: boolean;
  allow_registration: boolean;
  logo_url: string | null;
  logo_width: number;
  turnstile_enabled: boolean;
  is_logged_in: boolean;
  current_user?: {
    id: number;
    display_name: string;
    email: string;
  };
  logout_redirect_url?: string;
  primary_color: string;
  button_background: string;
  button_text_color: string;
  secondary_button_background: string;
  secondary_button_text_color: string;
  tab_background: string;
  tab_text_color: string;
  active_tab_background: string;
  active_tab_text_color: string;
  card_background: string;
  overlay_background: string;
  heading_font: string;
  body_font: string;
  text_primary_color: string;
  text_secondary_color: string;
  text_muted_color: string;
  text_label_color: string;
  text_inverse_color: string;
  font_size_base: string;
  font_size_sm: string;
  font_size_xs: string;
  heading_size_h1: string;
  heading_size_h2: string;
  line_height_base: string;
  card_radius: string;
  card_shadow: string;
  card_padding: string;
  logged_in_card_padding: string;
  modal_padding: string;
  form_gap: string;
  section_gap: string;
  input_background: string;
  input_text_color: string;
  input_placeholder_color: string;
  input_border_color: string;
  input_border_color_focus: string;
  input_border_width: string;
  input_radius: string;
  input_focus_ring_color: string;
  input_focus_ring_width: string;
  input_disabled_bg: string;
  input_disabled_text: string;
  input_error_border: string;
  input_padding_x: string;
  input_padding_y: string;
  otp_box_bg: string;
  otp_box_text_color: string;
  otp_box_border_color: string;
  otp_box_border_color_filled: string;
  otp_box_border_color_active: string;
  otp_box_border_color_error: string;
  otp_box_border_width: string;
  otp_box_radius: string;
  otp_box_size_6_w: string;
  otp_box_size_6_h: string;
  otp_box_size_8_w: string;
  otp_box_size_8_h: string;
  otp_box_font_size: string;
  otp_box_gap: string;
  otp_box_disabled_bg: string;
  button_radius: string;
  button_padding_x: string;
  button_padding_y: string;
  button_hover_background: string;
  button_active_background: string;
  button_disabled_background: string;
  button_disabled_text: string;
  secondary_button_hover_background: string;
  secondary_button_active_background: string;
  destructive_button_background: string;
  destructive_button_text: string;
  destructive_button_hover_background: string;
  destructive_button_active_background: string;
  destructive_button_disabled_background: string;
  destructive_button_disabled_text: string;
  link_color: string;
  link_hover_color: string;
  link_decoration: string;
  link_hover_decoration: string;
  tab_radius: string;
  tab_padding_x: string;
  tab_padding_y: string;
  tab_border_width: string;
  tab_border_color_inactive: string;
  tab_border_color_active: string;
  notice_radius: string;
  notice_padding: string;
  notice_border_width: string;
  notice_error_bg: string;
  notice_error_border: string;
  notice_error_text: string;
  notice_success_bg: string;
  notice_success_border: string;
  notice_success_text: string;
  notice_info_bg: string;
  notice_info_border: string;
  notice_info_text: string;
  toast_success_bg: string;
  toast_error_bg: string;
  toast_info_bg: string;
  toast_text_color: string;
  toast_radius: string;
  toast_shadow: string;
  toast_padding_x: string;
  toast_padding_y: string;
  toast_max_width: string;
  toast_close_color: string;
  toast_close_hover_color: string;
  toast_close_bg: string;
  toast_close_hover_bg: string;
  magic_icon_bg: string;
  magic_icon_color: string;
  success_icon_bg: string;
  success_icon_color: string;
  logged_in_icon_bg: string;
  logged_in_icon_color: string;
  modal_icon_bg: string;
  modal_icon_color: string;
  icon_circle_size_sm: string;
  icon_circle_size_md: string;
  icon_size_sm: string;
  icon_size_md: string;
  modal_background: string;
  modal_text_color: string;
  modal_radius: string;
  modal_shadow: string;
  modal_overlay_color: string;
  modal_overlay_opacity: string;
  spinner_color: string;
  spinner_success_color: string;
  spinner_size_lg: string;
  spinner_size_sm: string;
  spinner_border_width: string;
  footer_text_color: string;
  footer_font_size: string;
  animation_enabled: boolean;
  animation_slide_in_duration: string;
  animation_scale_in_duration: string;
  transition_duration: string;
}

export interface AuthResponse {
  success: boolean;
  method: 'otp' | 'magic_link';
  message: string;
  expires_in?: number;
  email_sent?: boolean;
  otp_length?: number;
  otp_type?: OTPType;
  show_info_notice?: boolean;
}

export interface VerifyOTPResponse {
  success: boolean;
  user_exists: boolean;
  user_id?: number;
  redirect_url?: string;
  guest_token?: string;
  email?: string;
  message: string;
}

export interface ErrorResponse {
  success: false;
  message: string;
  reason?: string;
  show_as_info?: boolean;
}

export interface LogoutResponse {
  success: boolean;
  message: string;
  redirect_url?: string;
}

export interface WindowWithZFLData extends Window {
  zflData: {
    nonce: string;
    apiUrl: string;
  };
}

declare global {
  interface Window {
    zflData: {
      nonce: string;
      apiUrl: string;
    };
  }
}
