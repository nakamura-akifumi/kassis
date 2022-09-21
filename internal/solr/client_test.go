package solr

import (
	"context"
	"github.com/stretchr/testify/assert"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"testing"
)

func TestClient(t *testing.T) {
	testcorename := "kassiscore_test_itg"
	uri := "http://localhost:8983"

	ac, err := NewConnectionAndAdminClient(uri, http.DefaultClient)
	assert.Empty(t, err)
	if err != nil {
		t.Error(err)
		t.Fatal("failed test")
	}

	cr, err := ac.FindCoreByName(testcorename)
	assert.Empty(t, err)
	assert.Empty(t, cr)
	if err != nil {
		t.Error(err)
		t.Fatal("failed test")
	}

	err = ac.CopyConfigsetFromDefault(testcorename)
	assert.Empty(t, err)

	params := &url.Values{}
	params.Set("name", testcorename)
	_, err = ac.Action("CREATE", params)
	assert.Empty(t, err)

	cr, err = ac.FindCoreByName(testcorename)
	assert.Empty(t, err)
	assert.Equal(t, cr.Name, testcorename)
	assert.Equal(t, cr.Index.NumDocs, int64(0))

	schemafilename, _ := os.Getwd()
	schemafilename = filepath.Join(schemafilename, "..", "..", "tools", "kassis-solr-schema.json")
	err = ac.UpdateSolrSchema(testcorename, schemafilename)
	assert.Empty(t, err)

	params = &url.Values{}
	params.Add("core", testcorename)
	_, err = ac.Action("RELOAD", params)
	assert.Empty(t, err)

	// create client instance
	sc, err := NewConnectionAndSingleClient(uri, testcorename, http.DefaultClient)
	assert.Empty(t, err)

	ctx := context.Background()
	vs, qt, err := sc.Ping(ctx)
	assert.Empty(t, err)
	assert.Equal(t, vs, "a")
	assert.NotEqual(t, qt, -1)

	// create data

	// query

	// delete core
	params = &url.Values{}
	params.Add("core", testcorename)
	params.Add("deleteIndex", "true")
	params.Add("deleteDataDir", "true")
	params.Add("deleteInstanceDir", "true")
	_, err = ac.Action("UNLOAD", params)
	assert.Empty(t, err)

}
