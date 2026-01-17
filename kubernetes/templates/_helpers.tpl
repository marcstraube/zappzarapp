{{/*
Expand the name of the chart.
*/}}
{{- define "zappzarapp.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
We truncate at 63 chars because some Kubernetes name fields are limited to this (by the DNS naming spec).
*/}}
{{- define "zappzarapp.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "zappzarapp.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "zappzarapp.labels" -}}
helm.sh/chart: {{ include "zappzarapp.chart" . }}
{{ include "zappzarapp.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "zappzarapp.selectorLabels" -}}
app.kubernetes.io/name: {{ include "zappzarapp.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Component labels - extends common labels with component name
*/}}
{{- define "zappzarapp.componentLabels" -}}
{{ include "zappzarapp.labels" . }}
app.kubernetes.io/component: {{ .component }}
{{- end }}

{{/*
Component selector labels
*/}}
{{- define "zappzarapp.componentSelectorLabels" -}}
{{ include "zappzarapp.selectorLabels" . }}
app.kubernetes.io/component: {{ .component }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "zappzarapp.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "zappzarapp.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
Get the image repository with optional registry prefix
*/}}
{{- define "zappzarapp.imageRepository" -}}
{{- $registry := .global.imageRegistry | default "" -}}
{{- $repository := .image.repository -}}
{{- if $registry }}
{{- printf "%s/%s" $registry $repository }}
{{- else }}
{{- $repository }}
{{- end }}
{{- end }}

{{/*
Get the image tag
*/}}
{{- define "zappzarapp.imageTag" -}}
{{- .image.tag | default .global.imageTag | default .appVersion }}
{{- end }}

{{/*
Create namespace name
*/}}
{{- define "zappzarapp.namespace" -}}
{{- if .Values.namespace.create }}
{{- .Values.namespace.name | default (include "zappzarapp.fullname" .) }}
{{- else }}
{{- .Release.Namespace }}
{{- end }}
{{- end }}

{{/*
Database host based on enabled database
*/}}
{{- define "zappzarapp.databaseHost" -}}
{{- if .Values.postgres.enabled }}
{{- printf "%s-postgres" (include "zappzarapp.fullname" .) }}
{{- else if .Values.mariadb.enabled }}
{{- printf "%s-mariadb" (include "zappzarapp.fullname" .) }}
{{- else }}
{{- .Values.config.database.host | default "postgres" }}
{{- end }}
{{- end }}

{{/*
Database port based on enabled database
*/}}
{{- define "zappzarapp.databasePort" -}}
{{- if .Values.postgres.enabled }}
{{- "5432" }}
{{- else if .Values.mariadb.enabled }}
{{- "3306" }}
{{- else }}
{{- "5432" }}
{{- end }}
{{- end }}
