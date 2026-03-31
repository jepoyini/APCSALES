// action
import {
  registerUserSuccessful,
  registerUserFailed,
  resetRegisterFlagChange,
} from "./reducer";
import { postRegister } from "../../../helpers/auth_api";

// Is user register successfull then direct plot user in redux.
export const registerUser = (user : any) => async (dispatch : any) => {
  try {
    const response: any = await postRegister({
      email: user.email,
      username: user.username,
      firstname: user.firstname,
      lastname: user.lastname,
      phone: user.phone,
      password: user.password,
      confirm_password: user.confirm_password,
      country: user.country,
      sponsor_id: user.sponsor_id ?? "",
    });

    if (response?.status === "success") {
      dispatch(registerUserSuccessful(response));
      return;
    }

    dispatch(registerUserFailed(response?.message || "Registration failed"));
  } catch (error: any) {
    dispatch(registerUserFailed(error || "Registration failed"));
  }
};

export const resetRegisterFlag = () => {
  try {
    const response = resetRegisterFlagChange();
    return response;
  } catch (error) {
    return error;
  }
};
