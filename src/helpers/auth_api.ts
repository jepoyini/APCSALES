import { APIClient } from "./api_helper";
import * as url from "./url_helper";

const api = new APIClient();

export const postLogin = (data: { email: string; password: string }) =>
  api.create(url.POST_LOGIN, data);

export const postForgotPassword = (data: { email: string }) =>
  api.create(url.POST_FORGOT_PASSWORD, data);

export const postRegister = (data: {
  email: string;
  username: string;
  firstname: string;
  lastname: string;
  phone: string;
  password: string;
  confirm_password: string;
  country: string;
  sponsor_id: string;
}) => api.create(url.POST_REGISTER, data);
