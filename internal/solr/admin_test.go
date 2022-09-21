package solr

import (
	"github.com/stretchr/testify/assert"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"testing"
)

func TestAdminPing(t *testing.T) {
	vs, sh, err := AdminPing("http://localhost:8983")
	assert.Empty(t, err)
	assert.Equal(t, vs, "8.11.2")
	assert.Contains(t, sh, "server\\solr")

	vs, sh, err = AdminPing("http://localhost:19999")
	assert.Contains(t, err.Error(), "No connection could be")
	assert.Empty(t, vs)
	assert.Empty(t, sh)
}

func TestNewConnectionAndAdminClient(t *testing.T) {
	ac, err := NewConnectionAndAdminClient("http://localhost:19999", http.DefaultClient)
	assert.Contains(t, err.Error(), "No connection could be")
	assert.Empty(t, ac)

}

func TestAdminClient(t *testing.T) {
	testcorename := "kassiscore_test_itg"

	ac, err := NewConnectionAndAdminClient("http://localhost:8983", http.DefaultClient)
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

	params = &url.Values{}
	params.Add("name", testcorename)
	_, err = ac.Action("STATUS", params)
	assert.Empty(t, err)

	params = &url.Values{}
	params.Add("core", testcorename)
	params.Add("deleteIndex", "true")
	params.Add("deleteDataDir", "true")
	params.Add("deleteInstanceDir", "true")
	_, err = ac.Action("UNLOAD", params)
	assert.Empty(t, err)

	cr, err = ac.FindCoreByName(testcorename)
	assert.Empty(t, err)
	assert.Empty(t, cr)

}
