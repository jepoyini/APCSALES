import React, { useEffect } from "react";
import { Navigate } from "react-router-dom";
import { setAuthorization } from "../helpers/api_helper";
import AccessDenied from "../Components/Common/AccessDenied";
import { hasPermission } from "../helpers/permissions";

import { useProfile } from "../Components/Hooks/UserHooks";

const AuthProtected = (props : any) =>{
  const { userProfile, loading, token } = useProfile();
  
  useEffect(() => {
    if (userProfile && !loading && token) {
      setAuthorization(token);
    }
  }, [token, userProfile, loading]);

  /*
    Navigate is un-auth access protected routes via url
    */
  if (loading) {
    return null;
  }

  if (!userProfile) {
    return (
      <Navigate to="/login" replace />
    );
  }

  if (!hasPermission(userProfile, props.permission)) {
    return <AccessDenied />;
  }

  return <>{props.children}</>;
};


export default AuthProtected;
