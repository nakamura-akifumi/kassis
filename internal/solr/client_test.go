package solr

import (
	"context"
	"encoding/json"
	"fmt"
	"github.com/stretchr/testify/assert"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"testing"
)

type TestDocument struct {
	ID                 string   `json:"id"`
	Identifiers        []string `json:"identifiers"`
	Materialid         string   `json:"materialid"`
	Objecttype         string   `json:"objecttype"`
	Mediatype          string   `json:"mediatype"`
	Title              string   `json:"title"`
	TitleTranscription string   `json:"title_transcription"`
}

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
	if err != nil {
		t.Error(err)
		t.Fatal("failed test")
	}
	// 途中でこけて残っている可能性なので削除
	if cr.Name == testcorename {
		fmt.Println("clean up core")
		ac.ForceUnload(testcorename)
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
	schemafilename = filepath.Join(schemafilename, "..", "..", "testdata", "kassis-solr-test_schema.json")
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
	assert.Equal(t, vs, "OK")
	assert.NotEqual(t, qt, -1)

	// create data
	var doc1 = TestDocument{
		ID:         "1",
		Materialid: "m1",
		Mediatype:  "BOOK",
		Objecttype: "MANIFESTATION",
		Title:      "巴波川の生き物たちその１",
	}
	var doc2 = TestDocument{
		ID:         "2",
		Materialid: "m2",
		Mediatype:  "BOOK",
		Objecttype: "MANIFESTATION",
		Title:      "新潟うまいもの探訪記",
	}
	var doc3 = TestDocument{
		ID:         "3",
		Materialid: "m3",
		Mediatype:  "CD",
		Objecttype: "MANIFESTATION",
		Title:      "面白い・ロールプレイングゲーム・クラシック・２",
	}

	docs := []TestDocument{doc1, doc2, doc3}

	opts := WriteOptions{Commit: true}
	_, err = sc.BulkCreate(ctx, docs, &opts)
	assert.Empty(t, err)

	// query
	q := NewQuery()
	q.Q("*:*")

	q.SetParam("hl", "true")
	q.SetParam("hl.fl", "contents")
	q.SetParam("hl.simple.pre", "<em>")
	q.SetParam("hl.simple.post", "</em>")

	r, err := sc.Search(ctx, q)

	var results []*TestDocument
	fBytes, err := r.Data.Docs.ToBytes()
	assert.Empty(t, err)
	err = json.Unmarshal(fBytes, &results)
	assert.Empty(t, err)

	assert.Equal(t, len(results), 3)

	//TODO: 検索してハイライト
	q = NewQuery()
	q.Q("title:*ゲーム*") //TODO: title:ゲーム

	q.SetParam("hl", "true")
	q.SetParam("hl.fl", "contents")
	q.SetParam("hl.simple.pre", "<em>")
	q.SetParam("hl.simple.post", "</em>")

	r, err = sc.Search(ctx, q)

	fBytes, err = r.Data.Docs.ToBytes()
	assert.Empty(t, err)
	err = json.Unmarshal(fBytes, &results)
	assert.Empty(t, err)

	assert.Equal(t, len(results), 1)
	assert.Equal(t, results[0].Title, doc3.Title)

	// delete core
	params = &url.Values{}
	params.Add("core", testcorename)
	params.Add("deleteIndex", "true")
	params.Add("deleteDataDir", "true")
	params.Add("deleteInstanceDir", "true")
	_, err = ac.Action("UNLOAD", params)
	assert.Empty(t, err)

}
