import { postLogin } from "../../../helpers/auth_api";
import { clearAuthUser, setAuthUser } from "../../../helpers/auth_storage";

import { loginSuccess, logoutUserSuccess, apiError, reset_login_flag } from './reducer';

export const loginUser = (user : any, history : any) => async (dispatch : any) => {
  try {
    const response: any = await postLogin({
      email: user.email,
      password: user.password,
    });

    if (response?.status === "success" && response?.data) {
      const authPayload = {
        ...response.data,
        token: response.data.token || response.data.csrf_token,
      };
      setAuthUser(authPayload);
      dispatch(loginSuccess(authPayload));
      history('/dashboard');
      return;
    }

    const errorMessage =
      response?.message ||
      (typeof response?.data === "string" ? response.data : null) ||
      "Invalid email or password";
    dispatch(apiError({ data: errorMessage }));
  } catch (error) {
    const message = typeof error === "string" ? error : "Login failed";
    dispatch(apiError({ data: message }));
  }
};

export const logoutUser = () => async (dispatch : any) => {
  try {
    clearAuthUser();
    dispatch(logoutUserSuccess(true));
  } catch (error) {
    const message = typeof error === "string" ? error : "Logout failed";
    dispatch(apiError({ data: message }));
  }
};

export const resetLoginFlag = () => async (dispatch : any) => {
  try {
    const response = dispatch(reset_login_flag());
    return response;
  } catch (error) {
    dispatch(apiError(error));
  }
};
