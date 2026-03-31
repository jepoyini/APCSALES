
import { createSlice } from "@reduxjs/toolkit";

export const initialState = {
  registrationError: null,
  message: null,
  loading: false,
  user: null,
  success: false,
  error: false,
  isUserLogout: true
};

const registerSlice = createSlice({
  name: "register",
  initialState,
  reducers: {
    registerUserSuccessful(state : any, action : any) {
      state.user = action.payload;
      state.message = action.payload?.message || null;
      state.loading = false;
      state.success = true;
      state.registrationError = null;
      state.error = false;
    },
    registerUserFailed(state : any, action : any) {
      state.user = null;
      state.loading = false;
      state.registrationError = action.payload;
      state.error = true;
      state.success = false;
    },
    resetRegisterFlagChange(state : any) {
      state.success = false;
      state.error = false;
      state.registrationError = null;
    },
    apiErrorChange(state : any, action : any){
      state.error = action.payload;
      state.loading = false;
      state.isUserLogout = false;
    }
  }
});

export const {
  registerUserSuccessful,
  registerUserFailed,
  resetRegisterFlagChange,
  apiErrorChange
} = registerSlice.actions;

export default registerSlice.reducer;
