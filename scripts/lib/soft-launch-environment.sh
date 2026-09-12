#!/bin/sh
# Shared outside-repository environment loading for the APP-0053 soft launch.
#
# Conversation provider settings are owned by soft-launch-conversation.env.
# The main soft-launch.env owns the application environment, provider driver,
# and live-request kill switch. This keeps endpoint/evidence configuration in
# one file while preserving a one-line operational disable control.

load_soft_launch_environment() {
  soft_launch_runtime="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
  conversation_workspace="${GE_CONVERSATION_MODEL_ROOT:-${HOME}/ge-conversation-model}"
  soft_launch_env_file="${GE_SOFT_LAUNCH_ENV:-${soft_launch_runtime}/env/soft-launch.env}"
  conversation_env_file="${GE_SOFT_LAUNCH_CONVERSATION_ENV:-${conversation_workspace}/env/soft-launch-conversation.env}"

  soft_app_env_set=false
  soft_provider_driver_set=false
  soft_live_switch_set=false
  soft_app_env=''
  soft_provider_driver=''
  soft_live_switch=''

  set -a
  if [ -f "${soft_launch_env_file}" ]; then
    # shellcheck disable=SC1090
    . "${soft_launch_env_file}"
    if [ "${APP_ENV+x}" = x ]; then
      soft_app_env_set=true
      soft_app_env="${APP_ENV}"
    fi
    if [ "${CONVERSATION_PROVIDER_DRIVER+x}" = x ]; then
      soft_provider_driver_set=true
      soft_provider_driver="${CONVERSATION_PROVIDER_DRIVER}"
    fi
    if [ "${CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED+x}" = x ]; then
      soft_live_switch_set=true
      soft_live_switch="${CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED}"
    fi
  fi

  if [ -f "${conversation_env_file}" ]; then
    # shellcheck disable=SC1090
    . "${conversation_env_file}"
  fi

  if [ "${soft_app_env_set}" = true ]; then
    APP_ENV="${soft_app_env}"
  fi
  if [ "${soft_provider_driver_set}" = true ]; then
    CONVERSATION_PROVIDER_DRIVER="${soft_provider_driver}"
  fi
  if [ "${soft_live_switch_set}" = true ]; then
    CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED="${soft_live_switch}"
  fi

  APP_ENV="${APP_ENV:-soft-launch}"
  CONVERSATION_PROVIDER_DRIVER="${CONVERSATION_PROVIDER_DRIVER:-azure-openai-dev}"
  CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED="${CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED:-false}"
  export APP_ENV CONVERSATION_PROVIDER_DRIVER CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED
  set +a
}
