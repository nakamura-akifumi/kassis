package main

import (
	"encoding/json"
	"fmt"
	"github.com/nakamura-akifumi/kassis"
	"github.com/stretchr/testify/assert"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestHelloHandler(t *testing.T) {
	router := NewRouter()

	req := httptest.NewRequest("GET", "/", nil)
	rec := httptest.NewRecorder()

	router.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)
	assert.Contains(t, rec.Body.String(), "Hello, World!!")
}

func TestApiMaterialsQueryHandler(t *testing.T) {
	router := NewRouter()

	req := httptest.NewRequest("GET", "/api/materials", nil)
	rec := httptest.NewRecorder()

	params := req.URL.Query()
	params.Add("q", "ぽっぽ焼き")
	req.URL.RawQuery = params.Encode()

	router.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)

	//fmt.Println(rec.Body.String())

	jsonBytes := ([]byte)(rec.Body.String())
	data := new(kassiscore.KWRIF)

	if err := json.Unmarshal(jsonBytes, data); err != nil {
		fmt.Println("JSON Unmarshal error:", err)
		return
	}

	assert.Equal(t, "2022A1", data.Materials[0].Cellvalues[0])

}

func TestMaterialsQueryHandler(t *testing.T) {
	router := NewRouter()

	req := httptest.NewRequest("GET", "/materials", nil)
	rec := httptest.NewRecorder()

	params := req.URL.Query()
	params.Add("q", "ぽっぽ焼き")
	req.URL.RawQuery = params.Encode()

	router.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)

	fmt.Println(rec.Body.String())

	//assert.Equal(t, "2022A1", data.Materials[0].Cellvalues[0])
}
