import { userForgetPasswordError } from "./reducer"
import { postForgotPassword } from "../../../helpers/auth_api";
import { userForgetPasswordSuccess } from "./reducer";

export const userForgetPassword = (user : any, history : any) => async (dispatch : any) => {
  try {
    const response: any = await postForgotPassword({
      email: user.email,
    });

    if (response?.status === "success") {
      dispatch(userForgetPasswordSuccess(response?.message || "Password reset email sent."));
      return;
    }

    dispatch(userForgetPasswordError(response?.message || "No email found"));
  } catch (forgetError: any) {
    dispatch(userForgetPasswordError(forgetError || "Forgot password request failed"));
  }
}
