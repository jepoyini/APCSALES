// action
import { profileError, resetProfileFlagChange } from "./reducer";

export const editProfile = (user : any) => async (dispatch : any) => {
    dispatch(profileError("Profile update API endpoint is not configured."));
};

export const resetProfileFlag = () => {
    try {
        const response = resetProfileFlagChange();
        return response;
    } catch (error) {
        return error;
    }
};
